<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' - ' . config('app.name') : config('app.name') }}</title>

    {{-- Cropper.js --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />

    {{-- Flatpickr  --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200">

    {{-- NAVBAR mobile only --}}
    <x-nav sticky class="lg:hidden">
        <x-slot:brand>
            <x-app-brand />
        </x-slot:brand>
        <x-slot:actions>
            <label for="main-drawer" class="lg:hidden me-3">
                <x-icon name="o-bars-3" class="cursor-pointer" />
            </label>
        </x-slot:actions>
    </x-nav>

    {{-- MAIN --}}
    <x-main full-width>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

            {{-- BRAND --}}
            <x-app-brand class="p-5 pt-3" />

            {{-- MENU --}}
            <x-menu activate-by-route>

                {{-- User --}}
                @if ($user = auth()->user())
                    <x-menu-separator />

                    <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover
                        class="-mx-2 !-my-2 rounded">
                        <x-slot:actions>
                            <x-dropdown icon="o-cog-6-tooth">
                                <x-theme-toggle class="btn btn-circle " />
                                <x-button icon="o-power" class="btn-circle btn-error" tooltip-left="logoff"
                                    no-wire-navigate link="/logout" />
                            </x-dropdown>
                        </x-slot:actions>
                    </x-list-item>

                    <x-menu-separator />
                @endif

                {{-- <x-menu-item title="Home" icon="o-home" link="/" />
                <x-menu-item title="Users" icon="o-user-group" link="/users" />
                <x-menu-item title="Department" icon="o-building-office" link="/departments" />
                <x-menu-item title="Document Type" icon="o-view-columns" link="/documents/type" />
                <x-menu-item title="Document Submission" icon="o-document-plus" link="/documents-pengajuan" />
                <x-menu-item title="Document" icon="o-document-text" link="/documents" />
                <x-menu-item title="Document Approval" icon="o-check-circle" link="/documents-approval" />
                <x-menu-item title="Document Type" icon="o-view-columns" link="/documents/type" />
                <x-menu-item title="Document QrCode Stamper" icon="o-qr-code" link="/qrcodes" />
                <x-menu-item title="QrCode Generator" icon="o-plus-circle" link="/qrcodes/generate" /> --}}
                <x-menu-item title="Home" icon="o-home" link="/" />

                @if (auth()->user()->role === 'superadmin' || auth()->user()->role === 'admin')
                    <x-menu-item title="Users" icon="o-user-group" link="/users" />
                    <x-menu-item title="Settings" icon="o-wrench" link="/settings" />
                    <x-menu-item title="Department" icon="o-building-office" link="/departments" />
                @endif

                {{-- Menu yang bisa diakses semua role --}}
                <x-menu-item title="Profile" icon="o-user" link="/profile" />
                <x-menu-item title="Document" icon="o-document-text" link="/documents" />
                <x-menu-item title="Document Submission" icon="o-document-plus" link="/documents-pengajuan" />

                @if (in_array(auth()->user()->role, ['pimpinan', 'superadmin', 'admin']))
                    <x-menu-item title="Document Approval" icon="o-check-circle" link="/documents-approval" />
                @endif

                @if (in_array(auth()->user()->role, ['approver', 'superadmin', 'admin']))
                    <x-menu-item title="Document Type" icon="o-view-columns" link="/documents/type" />
                    <x-menu-item title="Document QrCode Stamper" icon="o-qr-code" link="/qrcodes" />
                    <x-menu-item title="QrCode Generator" icon="o-plus-circle" link="/qrcodes/generate" />
                @endif
                {{-- <x-menu-sub title="Settings" icon="o-cog-6-tooth">
                    <x-menu-item title="Wifi" icon="o-wifi" link="####" />
                    <x-menu-item title="Archives" icon="o-archive-box" link="####" />
                </x-menu-sub> --}}
            </x-menu>
        </x-slot:sidebar>

        {{-- The `$slot` goes here --}}
        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-main>

    {{--  TOAST area --}}
    <x-toast />
</body>

</html>
