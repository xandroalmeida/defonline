{{-- Telefone com DDD: (11) 98888-7777. Quem recebe o valor guarda só os dígitos. --}}
<x-input type="text"
         inputmode="tel" autocomplete="tel"
         maxlength="16" placeholder="(11) 98888-7777"
         x-data
         x-on:input="
             const d = $el.value.replace(/\D/g, '').slice(0, 11);
             let m = d;
             if (d.length > 10)      m = '('+d.slice(0,2)+') '+d.slice(2,7)+'-'+d.slice(7);
             else if (d.length > 6)  m = '('+d.slice(0,2)+') '+d.slice(2,6)+'-'+d.slice(6);
             else if (d.length > 2)  m = '('+d.slice(0,2)+') '+d.slice(2);
             else if (d.length > 0)  m = '('+d;
             $el.value = m;
         "
         {{ $attributes }}/>
