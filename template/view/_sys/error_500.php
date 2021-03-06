<?php declare(strict_types=1);
use DuckPhp\Helper\ViewHelper as V;

// change me if you can
//var_dump(get_defined_vars());

$is_debug = V::IsDebug();
$class = get_class($ex);
$code = $ex->getCode();
$message = $ex->getMessage();
$trace = $ex->getTraceAsString();

if ($is_debug) {
    ?>
<fieldset>
    <legend>Error(<?=$class ?>:<?=$code?>)</legend>
    <?=$message ?>
<pre>
--
<?=$trace?>
</pre>
</fieldset>
<?php
} else {
        ?>
    500
<?php
    }
