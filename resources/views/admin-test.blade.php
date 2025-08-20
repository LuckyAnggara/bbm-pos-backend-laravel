<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h1 class="text-3xl font-bold mb-6">Admin Panel - Backend Test</h1>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h2 class="text-xl font-semibold mb-2 text-blue-800">Laravel Setup</h2>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Laravel 10 Framework
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Sanctum Authentication
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Multi-tenancy Models
                                </li>
                            </ul>
                        </div>

                        <div class="bg-green-50 p-4 rounded-lg">
                            <h2 class="text-xl font-semibold mb-2 text-green-800">Admin Controllers</h2>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Dashboard Controller
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Tenant CRUD
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    User Management
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Support Tickets
                                </li>
                            </ul>
                        </div>

                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h2 class="text-xl font-semibold mb-2 text-purple-800">Frontend Setup</h2>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Inertia.js + Vue 3
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Tailwind CSS
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Admin Layout Components
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h2 class="text-2xl font-bold mb-4">Test Links</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <a href="/admin/dashboard" class="block p-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <div class="font-semibold">Admin Dashboard</div>
                                <div class="text-sm opacity-90">View admin dashboard with stats</div>
                            </a>
                            <a href="/admin/tenants" class="block p-4 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                <div class="font-semibold">Tenant Management</div>
                                <div class="text-sm opacity-90">Manage tenants and subscriptions</div>
                            </a>
                            <a href="/admin/users" class="block p-4 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                                <div class="font-semibold">User Management</div>
                                <div class="text-sm opacity-90">Manage users and roles</div>
                            </a>
                            <a href="/admin/support-tickets" class="block p-4 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                                <div class="font-semibold">Support Tickets</div>
                                <div class="text-sm opacity-90">Handle support requests</div>
                            </a>
                        </div>
                    </div>

                    <div class="mt-8 p-4 bg-yellow-50 rounded-lg">
                        <h3 class="font-semibold text-yellow-800 mb-2">Note:</h3>
                        <p class="text-yellow-700 text-sm">This is a test page to verify the admin panel backend is working. The frontend Vue components require npm install and build process.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>