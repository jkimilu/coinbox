<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
CoinBox
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
**CoinBox**: M-Pesa payment notifications for Kenyan businesses.<br>
Questions? [{{ config('coinbox.contact.support') }}](mailto:{{ config('coinbox.contact.support') }}) · Sales: [{{ config('coinbox.contact.sales') }}](mailto:{{ config('coinbox.contact.sales') }})

CoinBox never holds your customers’ payments. They go straight to your own PayBill or Till.<br>
© {{ date('Y') }} CoinBox
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
