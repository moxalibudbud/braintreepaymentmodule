braintree.dropin.create({
	authorization: BraintreePaymentsClientToken,
	selector: '#bt-dropin',
	paypal: {
		flow: 'vault'
	}
}, function (createErr, instance) {
	if (createErr) {
		console.log('Create Error', createErr);
		return;
	}

	$("#braintree-payment-form").on("submit", function(e){
		e.preventDefault();
		instance.requestPaymentMethod(function (err, payload) {
			if (err) {
				$("#braintree-payment-form #error").html(err.message).show();
				return;
			}

			$("#braintree-payment-form #error").html("").hide();
			document.querySelector('#nonce').value = payload.nonce; // Add the nonce to the form and submit
			$("#braintree-payment-form").unbind('submit').submit();
		});
	});
});


$("#braintree-payment-form").click(function(){
	$("#braintree-payment-form #error").html("").hide();
});