<template>
  <AdminLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Tenants</h1>
          <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
            Manage all tenants and their subscriptions.
          </p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
          <button @click="showCreateModal = true" class="btn-primary">
            Add Tenant
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div>
            <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
            <input v-model="search" @input="debouncedSearch" type="text" id="search" placeholder="Search tenants..." class="input-primary">
          </div>
          <div>
            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
            <select v-model="filters.status" @change="fetchTenants" id="status" class="input-primary">
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="trial">Trial</option>
              <option value="canceled">Canceled</option>
              <option value="past_due">Past Due</option>
            </select>
          </div>
          <div>
            <label for="plan" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Plan</label>
            <select v-model="filters.plan" @change="fetchTenants" id="plan" class="input-primary">
              <option value="">All Plans</option>
              <option value="basic">Basic</option>
              <option value="pro">Pro</option>
              <option value="enterprise">Enterprise</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Tenants Table -->
      <div class="card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Tenant
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Subscription
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Status
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Trial Ends
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Created
                </th>
                <th scope="col" class="relative px-6 py-3">
                  <span class="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                      <div class="h-10 w-10 rounded-full bg-primary-600 flex items-center justify-center">
                        <span class="text-sm font-medium text-white">{{ tenant.name.charAt(0) }}</span>
                      </div>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900 dark:text-white">{{ tenant.name }}</div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">{{ tenant.domain }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900 dark:text-white">
                    {{ tenant.subscription?.plan_name || 'No Plan' }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    ${{ tenant.subscription?.price || 0 }}/{{ tenant.subscription?.billing_cycle || 'month' }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                        :class="{
                          'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100': tenant.status === 'active',
                          'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100': tenant.status === 'trial',
                          'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100': tenant.status === 'canceled',
                          'bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100': tenant.status === 'past_due'
                        }">
                    {{ tenant.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ tenant.trial_ends_at ? formatDate(tenant.trial_ends_at) : '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(tenant.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <button @click="editTenant(tenant)" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300">
                      Edit
                    </button>
                    <button @click="viewTenant(tenant)" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300">
                      View
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between">
        <div class="flex-1 flex justify-between sm:hidden">
          <button @click="previousPage" :disabled="!tenants.prev_page_url" class="btn-secondary disabled:opacity-50">
            Previous
          </button>
          <button @click="nextPage" :disabled="!tenants.next_page_url" class="btn-secondary disabled:opacity-50">
            Next
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700 dark:text-gray-300">
              Showing {{ tenants.from }} to {{ tenants.to }} of {{ tenants.total }} results
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
              <button @click="previousPage" :disabled="!tenants.prev_page_url" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">
                Previous
              </button>
              <button @click="nextPage" :disabled="!tenants.next_page_url" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">
                Next
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showCreateModal || showEditModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>
        
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <form @submit.prevent="saveTenant">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="space-y-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                  {{ showCreateModal ? 'Create New Tenant' : 'Edit Tenant' }}
                </h3>
                
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                  <input v-model="form.name" type="text" id="name" required class="input-primary">
                </div>
                
                <div>
                  <label for="domain" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
                  <input v-model="form.domain" type="text" id="domain" class="input-primary">
                </div>
                
                <div>
                  <label for="contact_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Email</label>
                  <input v-model="form.contact_email" type="email" id="contact_email" class="input-primary">
                </div>
                
                <div>
                  <label for="plan" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Plan</label>
                  <select v-model="form.plan_name" id="plan" class="input-primary">
                    <option value="basic">Basic</option>
                    <option value="pro">Pro</option>
                    <option value="enterprise">Enterprise</option>
                  </select>
                </div>
                
                <div>
                  <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                  <select v-model="form.status" id="status" class="input-primary">
                    <option value="active">Active</option>
                    <option value="trial">Trial</option>
                    <option value="canceled">Canceled</option>
                    <option value="past_due">Past Due</option>
                  </select>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button type="submit" class="btn-primary sm:ml-3">
                {{ showCreateModal ? 'Create' : 'Update' }}
              </button>
              <button @click="closeModal" type="button" class="btn-secondary">
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  tenants: {
    type: Object,
    default: () => ({ data: [], from: 0, to: 0, total: 0 })
  }
})

const search = ref('')
const filters = reactive({
  status: '',
  plan: ''
})

const showCreateModal = ref(false)
const showEditModal = ref(false)
const editingTenant = ref(null)

const form = reactive({
  name: '',
  domain: '',
  contact_email: '',
  plan_name: 'basic',
  status: 'trial'
})

const breadcrumbs = [
  { text: 'Admin', href: '/admin' },
  { text: 'Tenants', href: '/admin/tenants' }
]

let searchTimeout = null

onMounted(() => {
  fetchTenants()
})

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchTenants()
  }, 300)
}

const fetchTenants = () => {
  router.get('/admin/tenants', {
    search: search.value,
    status: filters.status,
    plan: filters.plan
  }, {
    preserveState: true,
    preserveScroll: true
  })
}

const editTenant = (tenant) => {
  editingTenant.value = tenant
  form.name = tenant.name
  form.domain = tenant.domain
  form.contact_email = tenant.contact_email
  form.plan_name = tenant.subscription?.plan_name || 'basic'
  form.status = tenant.status
  showEditModal.value = true
}

const viewTenant = (tenant) => {
  router.visit(`/admin/tenants/${tenant.id}`)
}

const saveTenant = () => {
  if (showCreateModal.value) {
    router.post('/admin/tenants', form, {
      onSuccess: () => closeModal()
    })
  } else if (showEditModal.value) {
    router.put(`/admin/tenants/${editingTenant.value.id}`, form, {
      onSuccess: () => closeModal()
    })
  }
}

const closeModal = () => {
  showCreateModal.value = false
  showEditModal.value = false
  editingTenant.value = null
  Object.keys(form).forEach(key => {
    if (typeof form[key] === 'string') form[key] = ''
  })
  form.plan_name = 'basic'
  form.status = 'trial'
}

const previousPage = () => {
  if (props.tenants.prev_page_url) {
    router.visit(props.tenants.prev_page_url)
  }
}

const nextPage = () => {
  if (props.tenants.next_page_url) {
    router.visit(props.tenants.next_page_url)
  }
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}
</script>