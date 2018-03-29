{$environment}
<div class="wrapper">
    <div class="checkout container">
        <form method="post" id="braintree-payment-form" action="{$action}">
            <input id="amount" name="amount" type="hidden" value="{$amount}">
            <input name="currency" type="hidden" value="{$currency}">
            <input id="nonce" name="payment_method_nonce" type="hidden" />
            <div class="alert alert-danger" role="alert" id="error" style="display: none"></div>
            <div class="bt-drop-in-wrapper">
                <div id="bt-dropin"></div>
            </div>
        </form>
    </div>
</div>


<script src="https://js.braintreegateway.com/web/dropin/1.9.4/js/dropin.min.js"></script>
<script>
    var BraintreePaymentsClientToken = "{$client_token}";
</script>