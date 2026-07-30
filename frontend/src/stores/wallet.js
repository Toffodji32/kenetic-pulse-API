import { defineStore } from 'pinia'
import { ref } from 'vue'
import { API } from './auth'

export const useWalletStore = defineStore('wallet', () => {
  const wallet = ref(null)
  const transactions = ref([])
  const transactionsMeta = ref({ page: 1, total: 0, pages: 1 })
  const withdrawals = ref([])
  const loading = ref(false)

  async function fetchWallet() {
    const { data } = await API.get('/wallet')
    wallet.value = data
    return data
  }

  async function fetchTransactions(page = 1) {
    const { data } = await API.get('/wallet/transactions', { params: { page, limit: 20 } })
    transactions.value = data.data
    transactionsMeta.value = { page: data.page, total: data.total, pages: data.pages }
    return data
  }

  async function requestWithdrawal(amount, mobileMoneyNumber, mobileMoneyOperator) {
    const { data } = await API.post('/wallet/withdraw', {
      amount,
      mobileMoneyNumber,
      mobileMoneyOperator,
    })
    await fetchWallet()
    await fetchWithdrawals()
    return data
  }

  async function fetchWithdrawals() {
    const { data } = await API.get('/wallet/withdrawals')
    withdrawals.value = data
    return data
  }

  // Super admin
  async function fetchPendingWithdrawals(page = 1, status = 'pending') {
    const { data } = await API.get('/superadmin/withdrawals', { params: { status, page, limit: 20 } })
    return data
  }

  async function approveWithdrawal(id) {
    const { data } = await API.post(`/superadmin/withdrawals/${id}/approve`)
    return data
  }

  async function rejectWithdrawal(id, reason) {
    const { data } = await API.post(`/superadmin/withdrawals/${id}/reject`, { reason })
    return data
  }

  async function fetchAdminWallets() {
    const { data } = await API.get('/superadmin/wallets')
    return data
  }

  async function fetchWalletStats() {
    const { data } = await API.get('/superadmin/wallet-stats')
    return data
  }

  return {
    wallet, transactions, transactionsMeta, withdrawals, loading,
    fetchWallet, fetchTransactions, requestWithdrawal, fetchWithdrawals,
    fetchPendingWithdrawals, approveWithdrawal, rejectWithdrawal,
    fetchAdminWallets, fetchWalletStats,
  }
})
