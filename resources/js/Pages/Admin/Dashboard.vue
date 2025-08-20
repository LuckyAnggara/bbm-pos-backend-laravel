<template>
  <AdminLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
          <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
            Welcome to the admin panel. Here's an overview of your system.
          </p>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="flex items-center justify-center h-8 w-8 rounded-md bg-primary-500 text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
              </div>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                  Total Tenants
                </dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">
                  {{ stats.total_tenants }}
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div class="card p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="flex items-center justify-center h-8 w-8 rounded-md bg-green-500 text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
              </div>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                  Active Users
                </dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">
                  {{ stats.active_users }}
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div class="card p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="flex items-center justify-center h-8 w-8 rounded-md bg-yellow-500 text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
              </div>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                  Open Tickets
                </dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">
                  {{ stats.open_tickets }}
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div class="card p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="flex items-center justify-center h-8 w-8 rounded-md bg-blue-500 text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
              </div>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                  Monthly Revenue
                </dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">
                  ${{ stats.monthly_revenue }}
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card p-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
            Recent Tenants
          </h3>
          <div class="flow-root">
            <ul role="list" class="-mb-8">
              <li v-for="(tenant, index) in recentTenants" :key="tenant.id">
                <div class="relative pb-8" :class="{ 'pb-0': index === recentTenants.length - 1 }">
                  <div v-if="index !== recentTenants.length - 1" class="absolute top-5 left-5 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                  <div class="relative flex items-start space-x-3">
                    <div class="relative">
                      <div class="h-10 w-10 rounded-full bg-gray-400 flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                        <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4z" clip-rule="evenodd"></path>
                        </svg>
                      </div>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div>
                        <div class="text-sm">
                          <span class="font-medium text-gray-900 dark:text-white">{{ tenant.name }}</span>
                        </div>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                          {{ tenant.status }} • {{ formatDate(tenant.created_at) }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <div class="card p-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
            Recent Support Tickets
          </h3>
          <div class="flow-root">
            <ul role="list" class="-mb-8">
              <li v-for="(ticket, index) in recentTickets" :key="ticket.id">
                <div class="relative pb-8" :class="{ 'pb-0': index === recentTickets.length - 1 }">
                  <div v-if="index !== recentTickets.length - 1" class="absolute top-5 left-5 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                  <div class="relative flex items-start space-x-3">
                    <div class="relative">
                      <div class="h-10 w-10 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-gray-800"
                           :class="{
                             'bg-red-500': ticket.priority === 'high',
                             'bg-yellow-500': ticket.priority === 'medium',
                             'bg-green-500': ticket.priority === 'low'
                           }">
                        <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                      </div>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div>
                        <div class="text-sm">
                          <span class="font-medium text-gray-900 dark:text-white">{{ ticket.subject }}</span>
                        </div>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                          {{ ticket.status }} • {{ ticket.priority }} priority • {{ formatDate(ticket.created_at) }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      total_tenants: 0,
      active_users: 0,
      open_tickets: 0,
      monthly_revenue: 0
    })
  },
  recentTenants: {
    type: Array,
    default: () => []
  },
  recentTickets: {
    type: Array,
    default: () => []
  }
})

const breadcrumbs = [
  { text: 'Admin', href: '/admin' },
  { text: 'Dashboard', href: '/admin/dashboard' }
]

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}
</script>