#!/usr/bin/env bash
# Segredos versionados no git: arquivos locais em claro <-> cópias cifradas com SOPS em secrets/
# (mesmo mecanismo do ZalkVPS e do LeilaoFacil). Destinatários: chaves SSH públicas no .sops.yaml;
# decifra com a privada correspondente (padrão ~/.ssh/id_rsa; troque com DEF_SEGREDOS_CHAVE_SSH).
#
#   scripts/segredos.sh pull [--hook]   decifra secrets/ nos arquivos locais (3 vias, nunca sobrescreve edição local)
#   scripts/segredos.sh push            cifra os arquivos locais alterados em secrets/ (depois: commit)
#   scripts/segredos.sh status          estado de sincronia de cada arquivo
#   scripts/segredos.sh check           pre-commit: secrets/ staged cifrado + arquivos locais já enviados
#   scripts/segredos.sh verify          todo secrets/**/*.sops é SOPS válido, sem valor em claro
#   scripts/segredos.sh rekey           recifra para os destinatários atuais do .sops.yaml
#   scripts/segredos.sh instalar        sops fixado em .tools/bin (os hooks vêm do scripts/install-hooks.sh)
set -euo pipefail

SOPS_VERSION=3.13.3
# sha256 de sops-v3.13.3.checksums.txt (github.com/getsops/sops releases)
SHA_linux_amd64=e5bec3346a873ae91d871550f3e698c1aad962aff462a080e40f25fde17fef6b
SHA_linux_arm64=53b0abacd38ef1b12a66d6c100956691b9cefce018d91f81e73ddf7438b94d77
SHA_darwin_amd64=42162d5cef10b74fcf80a045a70e658d7ce6e63d6ea1be6f347e44015714468d
SHA_darwin_arm64=b97c0d434aab577dc40310e8d22ff9e45eef4c80638ab978daae9b4681c59286

# o formato dotenv do sops descarta linhas em branco; elas viajam como este comentário (cifrado)
MARCA_BRANCO='#~def-branco~'

RAIZ=$(git rev-parse --show-toplevel)
cd "$RAIZ"
ESTADO="$(git rev-parse --git-common-dir)/def-segredos"
SOPS="$RAIZ/.tools/bin/sops"
CHAVE=${DEF_SEGREDOS_CHAVE_SSH:-$HOME/.ssh/id_rsa}
export SOPS_AGE_SSH_PRIVATE_KEY_FILE="$CHAVE"
HOOK=0
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
trap 'rm -rf "$TMP"; exit 130' INT TERM
umask 077

# cópia cifrada : arquivo local : tipo sops (dotenv mantém o diff por variável).
mapeamento() {
	echo "secrets/env.prod.sops|.env.prod|dotenv"       # defonline-prod na ZalkVPS → secret APP_ENV_FILE (ADR-009)
	echo "secrets/app-env.sops|app/.env|dotenv"         # dev local (chaves de API reais, ex.: CNPJá)
}

diz() { if [[ $HOOK == 1 ]]; then printf 'def-segredos: %s\n' "$*" >&2; else printf '%s\n' "$*"; fi; }
morre() { printf 'def-segredos: %s\n' "$*" >&2; exit 1; }

sha() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | cut -d' ' -f1; else shasum -a 256 "$1" | cut -d' ' -f1; fi; }

instalar_sops() {
	if [[ -x $SOPS ]] && "$SOPS" --version --check-for-updates=false 2>/dev/null | grep -q "sops $SOPS_VERSION"; then return 0; fi
	local os arch esperado url
	os=$(uname -s | tr '[:upper:]' '[:lower:]')
	case "$(uname -m)" in x86_64 | amd64) arch=amd64 ;; arm64 | aarch64) arch=arm64 ;; *) morre "CPU não suportada $(uname -m)" ;; esac
	eval "esperado=\${SHA_${os}_${arch}:-}"
	[[ -n $esperado ]] || morre "plataforma não suportada $os/$arch"
	url="https://github.com/getsops/sops/releases/download/v$SOPS_VERSION/sops-v$SOPS_VERSION.$os.$arch"
	diz "instalando sops $SOPS_VERSION ($os/$arch) em .tools/bin"
	curl -fsSL -o "$TMP/sops" "$url" || morre "download falhou: $url"
	[[ $(sha "$TMP/sops") == "$esperado" ]] || morre "checksum não confere: $url"
	mkdir -p "$RAIZ/.tools/bin"
	chmod 755 "$TMP/sops"
	mv "$TMP/sops" "$SOPS"
}

tem_chave() { [[ -r $CHAVE ]]; }

# --- arquivo local ---------------------------------------------------------------------------------

existe_local() { [[ -f $1 ]]; }
ler_local() { cp "$1" "$2"; }
gravar_local() { # $1 origem, $2 local (modo 600)
	mkdir -p "$(dirname "$2")"
	cp "$1" "$2.def-tmp" && chmod 600 "$2.def-tmp" && mv "$2.def-tmp" "$2"
}
entrada() { echo "$1.incoming"; }
nome() { echo "$1"; }

# --- cifra -----------------------------------------------------------------------------------------

# dotenv: linha só de espaços ou arquivo sem \n no fim não voltam iguais do sops; o push recusa.
codificar() { if [[ $2 == dotenv ]]; then awk -v m="$MARCA_BRANCO" '/^[ \t]*$/ { print m; next } { print }' "$1"; else cat "$1"; fi; }
conferir_dotenv() {
	[[ ! -s $1 || $(tail -c1 "$1" | od -An -c | tr -d ' ') == '\n' ]] || morre "$2 não termina com quebra de linha: acrescente uma e rode de novo"
	! grep -qE '^[[:space:]]+$' "$1" || morre "$2 tem linha só com espaços: deixe-a vazia e rode de novo"
}
decodificar() { if [[ $2 == dotenv ]]; then awk -v m="$MARCA_BRANCO" '$0 == m { print ""; next } { print }' "$1"; else cat "$1"; fi; }

decifrar_em() { # $1 cifrado, $2 claro, $3 tipo
	"$SOPS" decrypt --input-type "$3" --output-type "$3" "$1" </dev/null >"$TMP/dec.raw" 2>"$TMP/err" ||
		morre "não consegui decifrar $1 com $CHAVE: $(head -c 300 "$TMP/err")"
	decodificar "$TMP/dec.raw" "$3" >"$2"
}

arq_base() { echo "$ESTADO/$(echo "$1" | tr '/' '_').sha256"; }
base_ler() { local f; f=$(arq_base "$1"); if [[ -f $f ]]; then cat "$f"; else echo nenhuma; fi; }
base_gravar() { mkdir -p "$ESTADO"; sha "$2" >"$(arq_base "$1")"; }

# --- comandos --------------------------------------------------------------------------------------

cmd_pull() {
	instalar_sops
	if ! tem_chave; then diz "sem chave privada em $CHAVE; nada decifrado"; return 0; fi
	local cif loc tipo remoto base inc
	while IFS='|' read -r cif loc tipo; do
		[[ -f $cif ]] || continue
		decifrar_em "$cif" "$TMP/remoto" "$tipo"
		remoto=$(sha "$TMP/remoto")
		base=$(base_ler "$cif")
		inc=$(entrada "$loc")
		if ! existe_local "$loc"; then
			gravar_local "$TMP/remoto" "$loc"; base_gravar "$cif" "$TMP/remoto"; diz "criado $(nome "$loc")"
			continue
		fi
		ler_local "$loc" "$TMP/local"
		if cmp -s "$TMP/remoto" "$TMP/local"; then
			base_gravar "$cif" "$TMP/local"; rm -f "$inc"
		elif [[ $(sha "$TMP/local") == "$base" ]]; then
			gravar_local "$TMP/remoto" "$loc"; base_gravar "$cif" "$TMP/remoto"; diz "atualizado $(nome "$loc") a partir de $cif"
		elif [[ $remoto == "$base" ]]; then
			diz "$(nome "$loc") tem mudanças locais não enviadas: rode 'scripts/segredos.sh push'"
		else
			# a versão que chegou vira a base: depois do merge (e de apagar o .incoming), o push é liberado
			mkdir -p "$(dirname "$inc")"
			cp "$TMP/remoto" "$inc"; chmod 600 "$inc"; base_gravar "$cif" "$TMP/remoto"
			diz "CONFLITO: $(nome "$loc") e $cif mudaram. Junte $inc em $(nome "$loc"), apague-o e rode 'scripts/segredos.sh push'"
		fi
	done < <(mapeamento)
}

cmd_push() {
	instalar_sops
	tem_chave || morre "sem chave privada em $CHAVE (necessária para comparar com a cópia cifrada)"
	local cif loc tipo
	while IFS='|' read -r cif loc tipo; do
		existe_local "$loc" || continue
		[[ -f $(entrada "$loc") ]] && morre "conflito pendente: junte e apague $(entrada "$loc")"
		ler_local "$loc" "$TMP/local"
		[[ $tipo == dotenv ]] && conferir_dotenv "$TMP/local" "$(nome "$loc")"
		codificar "$TMP/local" "$tipo" >"$TMP/codificado"
		if [[ -f $cif ]]; then
			decifrar_em "$cif" "$TMP/remoto" "$tipo"
			if cmp -s "$TMP/remoto" "$TMP/local"; then base_gravar "$cif" "$TMP/local"; continue; fi
			if [[ $(sha "$TMP/remoto") != "$(base_ler "$cif")" && ${FORCE:-0} != 1 ]]; then
				morre "$cif mudou desde o último pull (ou nunca foi puxado aqui): rode 'scripts/segredos.sh pull' antes (FORCE=1 ignora)"
			fi
		fi
		if [[ -f $cif && $tipo == dotenv ]]; then
			# sops edit preserva o ciphertext das variáveis que não mudaram: o diff mostra só o que mudou
			printf '#!/bin/sh\ncat "%s" > "$1"\n' "$TMP/codificado" >"$TMP/editor"
			chmod 700 "$TMP/editor"
			EDITOR="$TMP/editor" "$SOPS" edit --input-type dotenv --output-type dotenv "$cif" </dev/null
		else
			mkdir -p "$(dirname "$cif")"
			"$SOPS" encrypt --filename-override "$cif" --input-type "$tipo" --output-type "$tipo" "$TMP/codificado" </dev/null >"$TMP/novo"
			mv "$TMP/novo" "$cif"; chmod 644 "$cif"
		fi
		base_gravar "$cif" "$TMP/local"
		diz "cifrado $(nome "$loc") -> $cif"
	done < <(mapeamento)
}

cmd_status() {
	instalar_sops
	tem_chave || morre "sem chave privada em $CHAVE"
	local cif loc tipo estado
	while IFS='|' read -r cif loc tipo; do
		if [[ ! -f $cif ]] && ! existe_local "$loc"; then estado="ausente"
		elif [[ ! -f $cif ]]; then estado="ainda não cifrado (push)"
		elif ! existe_local "$loc"; then estado="não decifrado aqui (pull)"
		else
			decifrar_em "$cif" "$TMP/remoto" "$tipo"
			ler_local "$loc" "$TMP/local"
			if cmp -s "$TMP/remoto" "$TMP/local"; then estado="sincronizado"
			elif [[ $(sha "$TMP/local") == "$(base_ler "$cif")" ]]; then estado="cópia cifrada mais nova (pull)"
			else estado="mudanças locais não enviadas (push)"; fi
		fi
		[[ -f $(entrada "$loc") ]] && estado="$estado; CONFLITO pendente ($(entrada "$loc"))"
		printf '%-32s %-48s %s\n' "$cif" "$(nome "$loc")" "$estado"
	done < <(mapeamento)
}

# dotenv SOPS: toda linha é KEY=ENC[...], KEY= (vazio), #ENC[...] ou metadado sops_*, com MAC.
# binary SOPS: JSON com "data": "ENC[...]" e sops.mac.
conferir_cifrado() { # $1 arquivo, $2 caminho em secrets/ (padrão: $1)
	local caminho=${2:-$1}
	case $caminho in
		secrets/env.prod.sops | secrets/app-env.sops)
			grep -q '^sops_mac=ENC\[' "$1" || morre "$caminho não é um arquivo SOPS cifrado"
			local ruins
			ruins=$(grep -vnE '^[A-Za-z_][A-Za-z0-9_]*=$|^[A-Za-z_][A-Za-z0-9_]*=ENC\[AES256_GCM,|^#ENC\[AES256_GCM,|^sops_[A-Za-z0-9_]*=' "$1" | cut -d: -f1 | head -5 | tr '\n' ' ' || true)
			[[ -z $ruins ]] || morre "$caminho tem linhas em claro: $ruins" ;;
		*)
			jq -e 'keys == ["data","sops"] and (.data | startswith("ENC[AES256_GCM,")) and (.sops.mac | startswith("ENC[AES256_GCM,"))' "$1" >/dev/null 2>&1 ||
				morre "$caminho não é um arquivo SOPS binário cifrado" ;;
	esac
}

cmd_check() {
	local f vazados cif loc tipo staged
	# confere o que vai no commit (o índice), não a cópia do disco; --no-renames: renomeação conta como A
	staged=$(git diff --cached --name-only --no-renames --diff-filter=ACM) || morre "git diff falhou"
	while IFS= read -r f; do
		[[ $f == secrets/* ]] || continue
		git show ":$f" >"$TMP/staged" || morre "não li $f do índice"
		conferir_cifrado "$TMP/staged" "$f"
	done <<<"$staged"
	# nada em claro rastreado por engano (inclui o .incoming de conflito, temporários e backups)
	vazados=$(grep -v '^secrets/' <<<"$staged" | grep -E '(^|/)\.env(\.prod)?$|\.incoming$|\.def-tmp$' || true)
	[[ -z $vazados ]] || morre "arquivo em claro no commit: $vazados"
	[[ ${SKIP_SECRETS_CHECK:-0} == 1 ]] && return 0
	# só no clone principal: as worktrees dos executores não têm (nem precisam ter) os segredos
	[[ $(git rev-parse --git-dir) == "$(git rev-parse --git-common-dir)" ]] || return 0
	tem_chave || return 0
	instalar_sops
	while IFS='|' read -r cif loc tipo; do
		existe_local "$loc" || continue
		[[ -f $(entrada "$loc") ]] && morre "conflito de segredos pendente: $(entrada "$loc")"
		[[ -f $cif ]] || morre "$(nome "$loc") ainda não versionado: rode 'scripts/segredos.sh push' (SKIP_SECRETS_CHECK=1 ignora)"
		decifrar_em "$cif" "$TMP/remoto" "$tipo"
		ler_local "$loc" "$TMP/local"
		cmp -s "$TMP/remoto" "$TMP/local" || morre "$(nome "$loc") difere de $cif: rode 'scripts/segredos.sh push' (ou pull; SKIP_SECRETS_CHECK=1 ignora)"
	done < <(mapeamento)
}

cmd_verify() {
	local n=0 f
	while IFS= read -r f; do conferir_cifrado "$f"; n=$((n + 1)); done < <(find secrets -type f -name '*.sops' 2>/dev/null)
	echo "segredos verify: $n arquivo(s) cifrado(s) OK"
}

cmd_rekey() {
	instalar_sops
	local cif loc tipo
	while IFS='|' read -r cif loc tipo; do
		if [[ -f $cif ]]; then "$SOPS" updatekeys --yes --input-type "$tipo" "$cif" </dev/null; fi
	done < <(mapeamento)
}

case "${1:-}" in
	pull)
		if [[ ${2:-} == --hook ]]; then
			HOOK=1
			# hooks só no clone principal: worktree de executor não recebe segredo
			[[ $(git rev-parse --git-dir) == "$(git rev-parse --git-common-dir)" ]] || exit 0
			# checkout de commit antigo, bisect ou rebase não reinstalam segredo velho (chave já rotacionada)
			if [[ $(git symbolic-ref -q --short HEAD || true) != main ]]; then
				diz "fora da main: segredos não decifrados (rode 'scripts/segredos.sh pull' se quiser)"
				exit 0
			fi
		fi
		cmd_pull ;;
	push) cmd_push ;;
	status) cmd_status ;;
	check) HOOK=1; cmd_check ;;
	verify) cmd_verify ;;
	rekey) cmd_rekey ;;
	instalar)
		instalar_sops
		"$SOPS" --version --check-for-updates=false 2>/dev/null | head -1
		"$RAIZ/scripts/install-hooks.sh" ;;
	*) sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; exit 2 ;;
esac
