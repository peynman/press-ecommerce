<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body>
        <form id="redirectForm" action="{!! $url !!}" method="@php(config('larapress.ecommerce.banking.redirect.method'))">
            @foreach ($inputs as $key => $value)
                <input name="{!! $key !!}" value="{!! $value !!}" type="hidden" />
            @endforeach
        </form>
        <script type="text/javascript">
            document.getElementById('redirectForm').submit();
        </script>
    </body>
</html>
