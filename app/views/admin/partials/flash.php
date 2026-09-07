<?php /** @var array $flash */ ?>
<?php if (!empty($flash)): ?>
    <div class="ad-flashes">
        <?php foreach ($flash as $message): ?>
            <div class="ad-flash ad-flash--<?= e($message['type']) ?>" role="status">
                <?= icon($message['type'] === 'success' ? 'check' : 'shield', 'ad-flash__icon', 18) ?>
                <span><?= e($message['message']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
