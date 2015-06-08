<h2>Order Confirmation</h2>
{if $status == 'ok'}
	<p>{l s='Your order for' mod='paybeagle'} <span class="bold">{$shop_name}</span> {l s='is complete.' mod='paybeagle'}
		<br /><br /><span class="bold">{l s='Your order will be processed as soon as possible.' mod='paybeagle'}</span>
		<br /><br />{l s='For any questions or for further information, please contact our' mod='paybeagle'} <a href="{$base_dir_ssl}contact-form.php">{l s='customer support' mod='paybeagle'}</a>.
	</p>
{else}
	<p class="warning">
		Unfortunately payment has failed for your order. Please recomplete the checkout process.
	</p>
{/if}
