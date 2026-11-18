<?php

$title = is_string($title ?? null) ? $title : 'Vending Machine';
$content = is_string($content ?? null) ? $content : '';
$basePath = is_string($basePath ?? null) ? $basePath : '';
$assetUrl = is_string($assetUrl ?? null) ? $assetUrl : $basePath . '/assets/css/output.css';
$currentUser = is_array($currentUser ?? null) ? $currentUser : null;
$layoutSuccessMessage = is_string($layoutSuccessMessage ?? null) ? $layoutSuccessMessage : null;
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($title) ?> - Vending Machine</title>
    <link rel="stylesheet" href="<?= $escape($assetUrl) ?>">
</head>
<body class="flex min-h-screen flex-col bg-slate-100 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4" aria-label="Main navigation">
            <a class="text-lg font-bold text-slate-900" href="<?= $escape($basePath . '/products') ?>">Vending Machine</a>
            <div class="flex items-center gap-4">
                <a class="text-blue-700 underline" href="<?= $escape($basePath . '/products') ?>">Products</a>
                <?php if ($currentUser !== null): ?>
                    <a class="text-blue-700 underline" href="<?= $escape($basePath . '/transactions') ?>">Purchase history</a>
                    <?php if (($currentUser['role'] ?? null) === 'admin'): ?>
                        <a class="text-blue-700 underline" href="<?= $escape($basePath . '/admin/products/create') ?>">Create product</a>
                    <?php endif; ?>
                    <span class="text-sm text-slate-600"><?= $escape((string) ($currentUser['name'] ?? '')) ?></span>
                    <form action="<?= $escape($basePath . '/auth/logout') ?>" method="post">
                        <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                        <button type="submit" class="text-blue-700 underline">Log out</button>
                    </form>
                <?php else: ?>
                    <a class="text-blue-700 underline" href="<?= $escape($basePath . '/auth/login') ?>">Sign in</a>
                    <a class="text-blue-700 underline" href="<?= $escape($basePath . '/auth/register') ?>">Create account</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    <?php if ($layoutSuccessMessage !== null): ?>
        <div class="mx-auto mt-6 w-full max-w-5xl px-6">
            <p class="rounded bg-green-100 p-4 text-green-800" role="status"><?= $escape($layoutSuccessMessage) ?></p>
        </div>
    <?php endif; ?>
    <div class="flex-1">
        <?= $content ?>
    </div>
    <footer class="mt-10 border-t border-slate-200 bg-white">
        <p class="mx-auto max-w-5xl px-6 py-4 text-sm text-slate-600">Vending Machine</p>
    </footer>
</body>
</html>
