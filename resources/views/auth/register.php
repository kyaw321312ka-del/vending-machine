<?php

$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$basePath = is_string($basePath ?? null) ? $basePath : '';
$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>
<section class="mx-auto max-w-md px-6 py-10">
    <h1 class="mb-6 text-3xl font-bold">Create account</h1>

    <?php if (isset($errors['_general']) && is_string($errors['_general'])): ?>
        <p class="mb-4 rounded bg-red-100 p-4 text-red-800" role="alert"><?= $escape($errors['_general']) ?></p>
    <?php endif; ?>

    <form action="<?= $escape($basePath . '/auth/register') ?>" method="post" class="space-y-5 rounded bg-white p-6 shadow">
        <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">

        <div>
            <label for="name" class="mb-1 block font-medium">Name</label>
            <input
                id="name"
                name="name"
                type="text"
                maxlength="100"
                autocomplete="name"
                required
                value="<?= $escape(is_string($old['name'] ?? null) ? $old['name'] : '') ?>"
                class="w-full rounded border border-slate-300 px-3 py-2"
            >
        </div>

        <div>
            <label for="email" class="mb-1 block font-medium">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                maxlength="150"
                autocomplete="email"
                required
                value="<?= $escape(is_string($old['email'] ?? null) ? $old['email'] : '') ?>"
                class="w-full rounded border border-slate-300 px-3 py-2"
            >
        </div>

        <div>
            <label for="password" class="mb-1 block font-medium">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                minlength="8"
                autocomplete="new-password"
                required
                class="w-full rounded border border-slate-300 px-3 py-2"
            >
            <p class="mt-1 text-sm text-slate-600">Use at least 8 characters.</p>
        </div>

        <button type="submit" class="rounded bg-blue-700 px-4 py-2 font-medium text-white">Create account</button>
    </form>

    <p class="mt-5 text-sm">Already registered? <a class="text-blue-700 underline" href="<?= $escape($basePath . '/auth/login') ?>">Sign in</a>.</p>
</section>
