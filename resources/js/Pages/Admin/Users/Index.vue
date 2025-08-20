<template>
  <AdminLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Users</h1>
          <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
            Manage user accounts and their roles across all tenants.
          </p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
          <button @click="showCreateModal = true" class="btn-primary">
            Add User
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="card p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
          <div>
            <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
            <input v-model="search" @input="debouncedSearch" type="text" id="search" placeholder="Search users..." class="input-primary">
          </div>
          <div>
            <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
            <select v-model="filters.role" @change="fetchUsers" id="role" class="input-primary">
              <option value="">All Roles</option>
              <option value="admin">Admin</option>
              <option value="manager">Manager</option>
              <option value="cashier">Cashier</option>
              <option value="viewer">Viewer</option>
            </select>
          </div>
          <div>
            <label for="tenant" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tenant</label>
            <select v-model="filters.tenant_id" @change="fetchUsers" id="tenant" class="input-primary">
              <option value="">All Tenants</option>
              <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                {{ tenant.name }}
              </option>
            </select>
          </div>
          <div class="flex items-end">
            <button @click="clearFilters" class="btn-secondary">
              Clear Filters
            </button>
          </div>
        </div>
      </div>

      <!-- Users Table -->
      <div class="card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  User
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Role
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Tenant
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Branch
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
              <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                      <div class="h-10 w-10 rounded-full bg-primary-600 flex items-center justify-center">
                        <span class="text-sm font-medium text-white">{{ user.name.charAt(0) }}</span>
                      </div>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                        :class="{
                          'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100': user.role === 'admin',
                          'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100': user.role === 'manager',
                          'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100': user.role === 'cashier',
                          'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100': user.role === 'viewer'
                        }">
                    {{ user.role }}
                  </span>
                  <div v-if="user.is_tenant_owner" class="text-xs text-orange-600 mt-1">
                    Owner
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  {{ user.tenant?.name || 'No Tenant' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ user.branch?.name || 'No Branch' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(user.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <button @click="editUser(user)" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300">
                      Edit
                    </button>
                    <button @click="deleteUser(user)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" :disabled="user.user_type === 'super_admin'">
                      Delete
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
          <button @click="previousPage" :disabled="!users.prev_page_url" class="btn-secondary disabled:opacity-50">
            Previous
          </button>
          <button @click="nextPage" :disabled="!users.next_page_url" class="btn-secondary disabled:opacity-50">
            Next
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700 dark:text-gray-300">
              Showing {{ users.from }} to {{ users.to }} of {{ users.total }} results
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
              <button @click="previousPage" :disabled="!users.prev_page_url" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">
                Previous
              </button>
              <button @click="nextPage" :disabled="!users.next_page_url" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">
                Next
              </button>
            </nav>
          </div>
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
  users: {
    type: Object,
    default: () => ({ data: [], from: 0, to: 0, total: 0 })
  },
  tenants: {
    type: Array,
    default: () => []
  }
})

const search = ref('')
const filters = reactive({
  role: '',
  tenant_id: ''
})

const showCreateModal = ref(false)

const breadcrumbs = [
  { text: 'Admin', href: '/admin' },
  { text: 'Users', href: '/admin/users' }
]

let searchTimeout = null

onMounted(() => {
  fetchUsers()
})

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchUsers()
  }, 300)
}

const fetchUsers = () => {
  router.get('/admin/users', {
    search: search.value,
    role: filters.role,
    tenant_id: filters.tenant_id
  }, {
    preserveState: true,
    preserveScroll: true
  })
}

const clearFilters = () => {
  search.value = ''
  filters.role = ''
  filters.tenant_id = ''
  fetchUsers()
}

const editUser = (user) => {
  router.visit(`/admin/users/${user.id}/edit`)
}

const deleteUser = (user) => {
  if (user.user_type === 'super_admin') {
    alert('Cannot delete super admin users')
    return
  }
  
  if (confirm('Are you sure you want to delete this user?')) {
    router.delete(`/admin/users/${user.id}`)
  }
}

const previousPage = () => {
  if (props.users.prev_page_url) {
    router.visit(props.users.prev_page_url)
  }
}

const nextPage = () => {
  if (props.users.next_page_url) {
    router.visit(props.users.next_page_url)
  }
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}
</script>