<?php

use App\Config;
use App\Text;
?>
  </div>
</div>
<script defer src="<?= Text::e(Config::basePath()) ?>/admin/assets/admin.js?v=<?= Text::e((string) @filemtime(Config::publicPath('admin/assets/admin.js'))) ?>"></script>
</body>
</html>
