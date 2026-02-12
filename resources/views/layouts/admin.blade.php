<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .sidebar {
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-collapsed {
            width: 64px;
        }
        .sidebar-expanded {
            width: 220px;
        }
        .sidebar-icon {
            transition: transform 0.2s;
        }
        .sidebar-icon:hover {
            transform: scale(1.15);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Top Bar -->
    @include('layouts.topbar')
    <div class="flex min-h-screen">
        <!-- Animated Sidebar -->
        <aside id="sidebar" class="sidebar sidebar-expanded bg-white shadow-lg flex flex-col items-center py-6">
            <button id="toggleSidebar" class="mb-8 focus:outline-none">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <nav class="w-full">
                <ul class="space-y-4">
                    <li>
                        <a href="#" class="flex items-center px-4 py-2 rounded-lg text-gray-700 hover:bg-blue-50 sidebar-icon">
                            <span class="material-icons mr-3">dashboard</span>
                            <span class="sidebar-label">Home</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center px-4 py-2 rounded-lg text-gray-700 hover:bg-blue-50 sidebar-icon">
                            <span class="material-icons mr-3">people</span>
                            <span class="sidebar-label">Tenants</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center px-4 py-2 rounded-lg text-gray-700 hover:bg-blue-50 sidebar-icon">
                            <span class="material-icons mr-3">apartment</span>
                            <span class="sidebar-label">Apartments</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center px-4 py-2 rounded-lg text-gray-700 hover:bg-blue-50 sidebar-icon">
                            <span class="material-icons mr-3">payment</span>
                            <span class="sidebar-label">Payments</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center px-4 py-2 rounded-lg text-gray-700 hover:bg-blue-50 sidebar-icon">
                            <span class="material-icons mr-3">settings</span>
                            <span class="sidebar-label">Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 p-8">
            @yield('content')
        </main>
    </div>
    <script>
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggleSidebar');
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('sidebar-collapsed');
            sidebar.classList.toggle('sidebar-expanded');
            document.querySelectorAll('.sidebar-label').forEach(label => {
                label.classList.toggle('hidden');
            });
        });
        // Initial state: expanded
        sidebar.classList.add('sidebar-expanded');
        document.querySelectorAll('.sidebar-label').forEach(label => {
            label.classList.remove('hidden');
        });
    </script>
</body>
</html>