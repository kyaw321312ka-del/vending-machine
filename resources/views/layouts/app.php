<?php

$title = is_string($title ?? null) ? $title : 'Vending Machine';
$content = is_string($content ?? null) ? $content : '';
$basePath = is_string($basePath ?? null) ? $basePath : '';
$assetUrl = is_string($assetUrl ?? null) ? $assetUrl : $basePath . '/assets/css/output.css';
$currentUser = is_array($currentUser ?? null) ? $currentUser : null;
$layoutSuccessMessage = is_string($layoutSuccessMessage ?? null) ? $layoutSuccessMessage : null;
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$userName = $currentUser !== null && is_string($currentUser['name'] ?? null)
    ? $currentUser['name']
    : '';
$userInitial = $userName === '' ? 'U' : strtoupper(substr($userName, 0, 1));
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
<body class="flex min-h-screen flex-col bg-slate-50 text-slate-900 antialiased">
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8"
             aria-label="Main navigation">
            <a href="<?= $escape($basePath . '/products') ?>" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-lg font-bold text-white shadow-md">
                    V
                </span>
                <span class="text-xl font-extrabold tracking-tight text-slate-900">
                    Vending<span class="text-blue-600">Machine</span>
                </span>
            </a>

            <button
                type="button"
                id="mobile-menu-button"
                class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 md:hidden"
                aria-label="Toggle navigation"
                aria-expanded="false"
                aria-controls="mobile-navigation">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-6 w-6"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke="currentColor"
                     stroke-width="2"
                     aria-hidden="true">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="hidden items-center gap-2 md:flex">
                <a href="<?= $escape($basePath . '/products') ?>"
                   class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-blue-50 hover:text-blue-700">
                    Products
                </a>

                <?php if ($currentUser !== null): ?>
                    <a href="<?= $escape($basePath . '/transactions') ?>"
                       class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-blue-50 hover:text-blue-700">
                        Purchase History
                    </a>

                    <?php if (($currentUser['role'] ?? null) === 'admin'): ?>
                        <a href="<?= $escape($basePath . '/admin/products/create') ?>"
                           class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-blue-50 hover:text-blue-700">
                            Create Product
                        </a>
                    <?php endif; ?>

                    <div class="mx-2 h-8 border-l border-slate-200"></div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700">
                            <?= $escape($userInitial) ?>
                        </div>
                        <span class="text-sm font-semibold text-slate-700"><?= $escape($userName) ?></span>
                    </div>

                    <form action="<?= $escape($basePath . '/auth/logout') ?>" method="post">
                        <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                        <button type="submit"
                                class="ml-2 rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-red-50 hover:text-red-600">
                            Log out
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= $escape($basePath . '/auth/login') ?>"
                       class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-blue-50 hover:text-blue-700">
                        Sign in
                    </a>
                    <a href="<?= $escape($basePath . '/auth/register') ?>"
                       class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Create Account
                    </a>
                <?php endif; ?>
            </div>
        </nav>

        <div id="mobile-navigation" class="hidden border-t border-slate-200 bg-white px-5 py-4 md:hidden">
            <div class="flex flex-col gap-2">
                <a href="<?= $escape($basePath . '/products') ?>"
                   class="rounded-lg px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-blue-50 hover:text-blue-700">
                    Products
                </a>

                <?php if ($currentUser !== null): ?>
                    <a href="<?= $escape($basePath . '/transactions') ?>"
                       class="rounded-lg px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-blue-50 hover:text-blue-700">
                        Purchase History
                    </a>

                    <?php if (($currentUser['role'] ?? null) === 'admin'): ?>
                        <a href="<?= $escape($basePath . '/admin/products/create') ?>"
                           class="rounded-lg px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-blue-50 hover:text-blue-700">
                            Create Product
                        </a>
                    <?php endif; ?>

                    <div class="my-2 border-t border-slate-200"></div>
                    <span class="px-4 py-2 text-sm font-medium text-slate-500">
                        Signed in as <?= $escape($userName) ?>
                    </span>
                    <form action="<?= $escape($basePath . '/auth/logout') ?>" method="post">
                        <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                        <button type="submit"
                                class="w-full rounded-lg px-4 py-3 text-left text-sm font-semibold text-red-600 hover:bg-red-50">
                            Log out
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= $escape($basePath . '/auth/login') ?>"
                       class="rounded-lg px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-blue-50 hover:text-blue-700">
                        Sign in
                    </a>
                    <a href="<?= $escape($basePath . '/auth/register') ?>"
                       class="rounded-lg bg-blue-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-blue-700">
                        Create Account
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($layoutSuccessMessage !== null): ?>
        <div class="mx-auto mt-6 w-full max-w-7xl px-5 lg:px-8">
            <div class="flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800 shadow-sm"
                 role="status">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 font-bold"
                      aria-hidden="true">
                    &#10003;
                </span>
                <p class="text-sm font-medium"><?= $escape($layoutSuccessMessage) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="mx-auto w-full max-w-7xl flex-1 px-5 py-8 lg:px-8">
        <?= $content ?>
    </div>

    <footer class="mt-auto border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-5 py-6 text-sm text-slate-500 sm:flex-row lg:px-8">
            <p>&copy; <?= (int) date('Y') ?> Vending Machine. All rights reserved.</p>
            <p>Simple inventory and purchase management.</p>
        </div>
    </footer>

    <script>
        const menuButton = document.getElementById('mobile-menu-button');
        const mobileNavigation = document.getElementById('mobile-navigation');

        if (menuButton && mobileNavigation) {
            menuButton.addEventListener('click', () => {
                const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
                menuButton.setAttribute('aria-expanded', String(!isExpanded));
                mobileNavigation.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>
