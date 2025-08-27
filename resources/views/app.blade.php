<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Email Viewer') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div id="app" class="min-h-screen bg-gray-50">
        <header class="border-b bg-white">
            <div class="w-full px-4 py-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ config('app.name', 'Email Viewer') }}</h1>
            </div>
        </header>

        <div class="w-full h-[calc(100vh-72px)] px-4 py-4">
            <section class="h-full flex flex-col gap-3">
                <div class="bg-white border rounded p-3">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-700">Upload:</span>
                            <form id="upload-form" class="flex items-center gap-2" enctype="multipart/form-data">
                                <div class="relative">
                                    <input id="upload-input" type="file" name="files[]" class="text-sm" accept=".msg" multiple />
                                    <div id="file-count" class="absolute -top-2 -right-2 bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center hidden">0</div>
                                </div>
                                <button id="upload-btn" type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed">Upload</button>
                            </form>
                            <div id="upload-progress" class="hidden text-sm text-gray-600"></div>
                        </div>
                        
                        <form id="search-form" class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <label for="search" class="text-xs text-gray-600">Search:</label>
                                <input id="search" name="search" type="text" class="border rounded px-2 py-1 text-sm w-48" placeholder="Search emails..." />
                            </div>
                            <div class="flex items-center gap-2">
                                <label for="label_id" class="text-xs text-gray-600">Label:</label>
                                <select id="label_id" name="label_id" class="border rounded px-2 py-1 text-sm">
                                    <option value="">All labels</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2">
                                <label for="sort_by" class="text-xs text-gray-600">Sort:</label>
                                <select id="sort_by" name="sort_by" class="border rounded px-2 py-1 text-sm">
                                    <option value="sent_date">Date</option>
                                    <option value="subject">Subject</option>
                                    <option value="sender_email">Sender</option>
                                    <option value="file_size">File size</option>
                                </select>
                            </div>
                            <button type="submit" class="px-3 py-1 bg-gray-800 text-white rounded text-sm">Apply</button>
                        </form>
                    </div>
                </div>

                <div class="flex-1 flex gap-3">
                <div id="email-list" class="w-[360px] shrink-0 bg-white border rounded flex flex-col min-h-0">
                    <div class="border-b px-3 py-1.5 text-sm text-gray-600 flex items-center justify-between">
                        <div>
                            <span id="total-count">0</span> results
                        </div>
                        <div class="flex items-center gap-2">
                            <button id="prev-page" class="px-2 py-1 border rounded text-sm">Prev</button>
                            <span id="page-info" class="text-xs text-gray-500">1 / 1</span>
                            <button id="next-page" class="px-2 py-1 border rounded text-sm">Next</button>
                        </div>
                    </div>
                    <ul id="email-items" class="divide-y overflow-auto"></ul>
                    <div id="loading-indicator" class="hidden p-4 text-center text-gray-500">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Loading emails...
                    </div>
                </div>

                <div id="email-detail" class="flex-1 min-w-0 bg-white border rounded p-4 overflow-auto text-sm text-gray-700">
                    <div class="h-full flex items-center justify-center text-gray-400">
                        Select an email to view its contents
                    </div>
                </div>
            </section>
        </div>
    </div>
</body>
</html> 