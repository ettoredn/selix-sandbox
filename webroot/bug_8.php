<html><head></head><body>

<form action="?getvar=1" method="post">
	<input type="hidden" value="step3" name="form" />
	<input type="radio" name="language_type" value="multiple" />
	<input type="checkbox" name="languages[]" value="en" checked="checked" />
	<input type="checkbox" name="languages[]" value="fr" />
	<select id="language" name="language">
		<option value="en" selected="selected">English</option>
		<option value="fr">French</option>
	</select>
	<input type="submit" value="Next" />
</form>

<p><pre
<?php var_dump( $_POST ); var_dump( $_GET ); var_dump( $_ENV ); var_dump( $_SERVER ); ?>
</pre></p>

</body></html>