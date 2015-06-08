<!-- START PAYBEAGLE INTEGRATION -->
<div id="payBeagleHostedPlugin"></div>
<script type="text/javascript" src="{$this_path_paybeagle_include}"></script>
<script type="text/javascript">
payment = new PayBeagle({
 	'type'				:'REDIRECT',
 	'userID'			:'{$p["userID"]}',
 	'userPassword'		:'{$p["userPassword"]}',
 	'amount'			:'{$p["amount"]}',
 	'orderRef'			:'{$p["orderRef"]}',
 	'orderDescription'	:'Payment For {$shop_name} Cart'
});
</script>
<noscript>Sorry, you must enable javascript to use our payment system</noscript>
<!-- END PAYBEAGLE INTEGRATION -->

<p class="payment_module">
	<a href="#" class="bankwire" onclick="payment.execute(); return false;" title="{l s='Pay by Card' mod='paybeagle'}">
		<!--<img src="{$this_path_paybeagle}pb_small.png" alt="{l s='Pay by Card' mod='paybeagle'}" height="100" />style="padding: 3px 0px 3px 99px;"-->
		{l s='Pay by Card' mod='paybeagle'}
	</a>
</p>
