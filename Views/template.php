<?php

use kernel\Foundation\HTTP\Response\ResponseView;

?>

Hello world.
<div>
  <?php echo $name ?>
</div>
<p>
  <?php echo $age ?>
</p>
<?php ResponseView::section("section") ?>