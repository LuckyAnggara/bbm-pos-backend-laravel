<template>
  <AdminLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <!-- Header -->
      <div class="sm:flex sm:items-center sm:justify-between">
        <div class="sm:flex-auto">
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ tenant.name }}</h1>
          <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
            Tenant details and management
          </p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none space-x-3">
          <button @click="editTenant" class="btn-primary">
            Edit Tenant
          </button>
          <button @click="goBack" class="btn-secondary">
            Back to List
          </button>
        </div>
      </div>

      <!-- Tenant Overview -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Info -->
        <div class="lg:col-span-2">
          <div class="card p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Tenant Information</h3>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ tenant.name }}</dd>
              </div>
              
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Domain</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                  {{ tenant.domain || 'Not set' }}
                </dd>
              </div>
              
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Contact Email</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ tenant.contact_email }}</dd>
              </div>
              
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="mt-1">
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                        :class="{
                          'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100': tenant.status === 'active',
                          'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100': tenant.status === 'trial',
                          'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100': tenant.status === 'cancelled',
                          'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100': tenant.status === 'suspended',
                          'bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100': tenant.status === 'past_due'
                        }">
                    {{ tenant.status }}
                  </span>
                </dd>
              </div>
              
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Trial Ends</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                  {{ tenant.trial_ends_at ? formatDate(tenant.trial_ends_at) : 'N/A' }}
                </dd>
              </div>
              
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(tenant.created_at) }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Subscription Info -->
        <div>
          <div class="card p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Subscription</h3>
            <div v-if="tenant.subscription">
              <dl class="space-y-4">
                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Plan</dt>
                  <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                    {{ tenant.subscription.plan_name }}
                  </dd>
                </div>
                
                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Price</dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    ${{ tenant.subscription.price }}/{{ tenant.subscription.billing_cycle }}
                  </dd>
                </div>
                
                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                  <dd class="mt-1">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                          :class="{
                            'bg-green-100 text-green-800': tenant.subscription.status === 'active',
                            'bg-yellow-100 text-yellow-800': tenant.subscription.status === 'trial',
                            'bg-red-100 text-red-800': tenant.subscription.status === 'cancelled'
                          }">
                      {{ tenant.subscription.status }}
                    </span>
                  </dd>
                </div>
                
                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Features</dt>
                  <dd class="mt-1 space-y-1">
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                      <span class="inline-flex items-center">
                        <svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        Max {{ tenant.subscription.max_branches }} branches
                      </span>
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                      <span class="inline-flex items-center">
                        <svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        Max {{ tenant.subscription.max_users }} users
                      </span>
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                      <span class="inline-flex items-center">
                        <svg :class="['w-3 h-3 mr-1', tenant.subscription.has_inventory ? 'text-green-500' : 'text-gray-400']" fill="currentColor" viewBox="0 0 20 20">
                          <path v-if="tenant.subscription.has_inventory" fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                          <path v-else fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        Inventory Management
                      </span>
                    </div>
                  </dd>
                </div>
              </dl>
            </div>
            <div v-else class="text-sm text-gray-500 dark:text-gray-400">
              No active subscription
            </div>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 gap-5 sm:grid-cols-4">
        <div class="card p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
              </svg>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                  Branches
                </dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">
                  {{ tenant.branches?.length || 0 }}
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div class="card p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
              </svg>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                  Users
                </dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">
                  {{ tenant.users?.length || 0 }}
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div class="card p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
              </svg>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                  Support Tickets
                </dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">
                  {{ tenant.support_tickets?.length || 0 }}
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div class="card p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <svg class="h-8 w-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
              </svg>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                  Revenue
                </dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">
                  ${{ tenant.subscription?.price || 0 }}
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
            Recent Users
          </h3>
          <div class="flow-root">
            <ul role="list" class="-mb-8" v-if="tenant.users && tenant.users.length">
              <li v-for="(user, index) in tenant.users.slice(0, 5)" :key="user.id">
                <div class="relative pb-8" :class="{ 'pb-0': index === tenant.users.slice(0, 5).length - 1 }">
                  <div v-if="index !== tenant.users.slice(0, 5).length - 1" class="absolute top-5 left-5 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                  <div class="relative flex items-start space-x-3">
                    <div class="relative">
                      <div class="h-10 w-10 rounded-full bg-gray-400 flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                        <span class="text-sm font-medium text-white">{{ user.name.charAt(0) }}</span>
                      </div>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div>
                        <div class="text-sm">
                          <span class="font-medium text-gray-900 dark:text-white">{{ user.name }}</span>
                        </div>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                          {{ user.role }} • {{ formatDate(user.created_at) }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
            <div v-else class="text-sm text-gray-500 dark:text-gray-400">
              No users found
            </div>
          </div>
        </div>

        <div class="card p-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
            Recent Support Tickets
          </h3>
          <div class="flow-root">
            <ul role="list" class="-mb-8" v-if="tenant.support_tickets && tenant.support_tickets.length">
              <li v-for="(ticket, index) in tenant.support_tickets.slice(0, 5)" :key="ticket.id">
                <div class="relative pb-8" :class="{ 'pb-0': index === tenant.support_tickets.slice(0, 5).length - 1 }">
                  <div v-if="index !== tenant.support_tickets.slice(0, 5).length - 1" class="absolute top-5 left-5 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"></div>
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
            <div v-else class="text-sm text-gray-500 dark:text-gray-400">
              No support tickets found
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  tenant: {
    type: Object,
    required: true
  }
})

const breadcrumbs = [
  { text: 'Admin', href: '/admin' },
  { text: 'Tenants', href: '/admin/tenants' },
  { text: props.tenant.name, href: `/admin/tenants/${props.tenant.id}` }
]

const editTenant = () => {
  router.visit(`/admin/tenants/${props.tenant.id}/edit`)
}

const goBack = () => {
  router.visit('/admin/tenants')
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}
</script>