<template>
  <div class="wallet-page">
    <h1 class="page-title">Mon Wallet</h1>

    <!-- Balance cards -->
    <div class="balance-cards" v-if="store.wallet">
      <div class="card card-green">
        <div class="card-label">Disponible</div>
        <div class="card-amount">{{ formatAmount(store.wallet.balanceAvailable) }}</div>
        <div class="card-currency">FCFA</div>
      </div>
      <div class="card card-orange">
        <div class="card-label">En attente</div>
        <div class="card-amount">{{ formatAmount(store.wallet.balancePending) }}</div>
        <div class="card-currency">FCFA</div>
      </div>
      <div class="card card-blue">
        <div class="card-label">En cours de retrait</div>
        <div class="card-amount">{{ formatAmount(store.wallet.balancePendingWithdrawal) }}</div>
        <div class="card-currency">FCFA</div>
      </div>
      <div class="card card-purple">
        <div class="card-label">Gagné au total</div>
        <div class="card-amount">{{ formatAmount(store.wallet.totalEarned) }}</div>
        <div class="card-currency">FCFA</div>
      </div>
    </div>

    <!-- Withdraw button -->
    <div class="action-bar">
      <button class="btn btn-primary" @click="showDrawer = true" :disabled="!canWithdraw">
        Demander un retrait
      </button>
      <div v-if="!canWithdraw && store.wallet" class="hint">
        Solde disponible insuffisant (minimum {{ formatAmount(minAmount) }})
      </div>
    </div>

    <!-- Withdrawal drawer/modal -->
    <div v-if="showDrawer" class="modal-overlay" @click.self="closeDrawer">
      <div class="modal card">
        <h2>Demande de retrait</h2>

        <div v-if="withdrawSuccess" class="alert alert-success">
          ✅ Demande envoyée ! Vous recevrez les fonds sous 48h.
          <button @click="closeDrawer" class="btn btn-sm">Fermer</button>
        </div>

        <form v-else @submit.prevent="submitWithdraw">
          <div class="form-group">
            <label>Montant (FCFA)</label>
            <input v-model.number="withdrawForm.amount" type="number" min="1000" :max="store.wallet?.balanceAvailable || 0" required placeholder="1000" />
            <small>Min {{ formatAmount(minAmount) }} — Max {{ formatAmount(store.wallet?.balanceAvailable || 0) }}</small>
          </div>

          <div class="form-group">
            <label>Numéro Mobile Money</label>
            <input v-model="withdrawForm.number" type="tel" required placeholder="+229 97 XX XX XX" />
          </div>

          <div class="form-group">
            <label>Opérateur</label>
            <select v-model="withdrawForm.operator" required>
              <option value="">Sélectionner...</option>
              <option value="mtn">MTN</option>
              <option value="moov">Moov</option>
            </select>
          </div>

          <div class="recap" v-if="withdrawForm.amount >= minAmount">
            <p><strong>Récapitulatif</strong></p>
            <p>Montant : {{ formatAmount(withdrawForm.amount) }}</p>
            <p>Numéro : {{ withdrawForm.number || '—' }}</p>
            <p>Opérateur : {{ operatorLabel(withdrawForm.operator) }}</p>
          </div>

          <div class="form-actions">
            <button type="button" class="btn btn-secondary" @click="closeDrawer">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="submitting">
              {{ submitting ? 'Envoi en cours...' : 'Confirmer le retrait' }}
            </button>
          </div>

          <div v-if="withdrawError" class="alert alert-error">{{ withdrawError }}</div>
        </form>
      </div>
    </div>

    <!-- Transaction history -->
    <section class="section">
      <div class="section-header">
        <h2>Historique des transactions</h2>
      </div>
      <div class="card table-card">
        <table v-if="store.transactions.length">
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Description</th>
              <th class="text-right">Montant</th>
              <th class="text-right">Solde après</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tx in store.transactions" :key="tx.id">
              <td>{{ formatDate(tx.createdAt) }}</td>
              <td><span :class="'badge badge-' + tx.type">{{ typeLabel(tx.type) }}</span></td>
              <td>{{ tx.description }}</td>
              <td :class="'text-right ' + (tx.type === 'credit' || tx.type === 'refund' ? 'text-green' : 'text-red')">
                {{ tx.type === 'credit' || tx.type === 'refund' ? '+' : '-' }}{{ formatAmount(tx.amount) }}
              </td>
              <td class="text-right">{{ formatAmount(tx.balanceAfter) }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">Aucune transaction pour le moment.</div>
        <div class="pagination" v-if="store.transactionsMeta.pages > 1">
          <button @click="loadPage(txPage - 1)" :disabled="txPage <= 1">‹</button>
          <span>Page {{ txPage }} / {{ store.transactionsMeta.pages }}</span>
          <button @click="loadPage(txPage + 1)" :disabled="txPage >= store.transactionsMeta.pages">›</button>
        </div>
      </div>
    </section>

    <!-- My withdrawals -->
    <section class="section">
      <div class="section-header">
        <h2>Mes retraits</h2>
      </div>
      <div class="card table-card">
        <table v-if="store.withdrawals.length">
          <thead>
            <tr>
              <th>Date</th>
              <th>Montant</th>
              <th>Mobile Money</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="w in store.withdrawals" :key="w.id">
              <td>{{ formatDate(w.requestedAt) }}</td>
              <td>{{ formatAmount(w.amount) }}</td>
              <td>{{ (w.mobileMoneyOperator || '').toUpperCase() }} {{ w.mobileMoneyNumber }}</td>
              <td><span :class="'badge badge-' + w.status">{{ statusLabel(w.status) }}</span></td>
            </tr>
          </tbody>
        </table>
        <div v-else class="empty">Aucun retrait pour le moment.</div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useWalletStore } from '@/stores/wallet'

const store = useWalletStore()
const minAmount = 1000

const showDrawer = ref(false)
const submitting = ref(false)
const withdrawSuccess = ref(false)
const withdrawError = ref('')
const withdrawForm = ref({ amount: 1000, number: '', operator: '' })
const txPage = ref(1)

const canWithdraw = computed(() => store.wallet && store.wallet.balanceAvailable >= minAmount)

function formatAmount(v) { return (v || 0).toLocaleString('fr-FR') + ' FCFA' }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—' }
function typeLabel(t) { return { credit: 'Crédit', debit: 'Débit', commission: 'Commission', withdrawal: 'Retrait', refund: 'Remboursement' }[t] || t }
function statusLabel(s) { return { pending: 'En attente', approved: 'Approuvé', processing: 'En cours', completed: 'Effectué', rejected: 'Rejeté' }[s] || s }
function operatorLabel(o) { return { mtn: 'MTN', moov: 'Moov' }[o] || o }

function closeDrawer() {
  showDrawer.value = false
  withdrawSuccess.value = false
  withdrawError.value = ''
  withdrawForm.value = { amount: 1000, number: '', operator: '' }
}

async function submitWithdraw() {
  submitting.value = true
  withdrawError.value = ''
  try {
    await store.requestWithdrawal(withdrawForm.value.amount, withdrawForm.value.number, withdrawForm.value.operator)
    withdrawSuccess.value = true
  } catch (e) {
    withdrawError.value = e.response?.data?.error || 'Erreur lors de la demande'
  } finally {
    submitting.value = false
  }
}

async function loadPage(page) {
  txPage.value = page
  await store.fetchTransactions(page)
}

onMounted(async () => {
  await Promise.all([
    store.fetchWallet(),
    store.fetchTransactions(),
    store.fetchWithdrawals(),
  ])
})
</script>

<style scoped>
.page-title { margin-bottom: 1.5rem; font-size: 1.5rem; }
.balance-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.card { border-radius: 12px; padding: 1.5rem; }
.card-label { font-size: .85rem; color: #666; margin-bottom: .25rem; }
.card-amount { font-size: 1.5rem; font-weight: 700; }
.card-currency { font-size: .75rem; color: #999; }
.card-green { background: #ecfdf5; border: 1px solid #a7f3d0; }
.card-orange { background: #fff7ed; border: 1px solid #fed7aa; }
.card-blue { background: #eff6ff; border: 1px solid #bfdbfe; }
.card-purple { background: #f5f3ff; border: 1px solid #ddd6fe; }
.action-bar { margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem; }
.hint { font-size: .85rem; color: #999; }
.btn { padding: .6rem 1.2rem; border: none; border-radius: 8px; cursor: pointer; font-size: .9rem; font-weight: 500; }
.btn-primary { background: #4f46e5; color: #fff; }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }
.btn-secondary { background: #e5e7eb; color: #333; }
.btn-sm { padding: .4rem .8rem; font-size: .8rem; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal { width: 100%; max-width: 480px; padding: 2rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: .85rem; font-weight: 600; color: #374151; margin-bottom: .25rem; }
.form-group input, .form-group select { width: 100%; padding: .6rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: .9rem; }
.form-group small { color: #999; font-size: .75rem; }
.recap { background: #f9fafb; padding: 1rem; border-radius: 8px; margin: 1rem 0; font-size: .9rem; }
.form-actions { display: flex; gap: .5rem; justify-content: flex-end; }
.alert { padding: .75rem 1rem; border-radius: 8px; margin-top: 1rem; font-size: .9rem; }
.alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.section { margin-bottom: 2rem; }
.section-header { margin-bottom: 1rem; }
.section-header h2 { font-size: 1.15rem; }
.table-card { overflow-x: auto; padding: 0; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: .75rem 1rem; text-align: left; font-size: .85rem; border-bottom: 1px solid #f3f4f6; }
th { font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: .75rem; background: #f9fafb; }
.text-right { text-align: right; }
.text-green { color: #059669; }
.text-red { color: #dc2626; }
.badge { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 500; }
.badge-credit, .badge-refund { background: #ecfdf5; color: #065f46; }
.badge-debit, .badge-withdrawal { background: #fef2f2; color: #991b1b; }
.badge-commission { background: #fef3c7; color: #92400e; }
.badge-pending, .badge-processing { background: #fff7ed; color: #9a3412; }
.badge-approved { background: #eff6ff; color: #1e40af; }
.badge-completed { background: #ecfdf5; color: #065f46; }
.badge-rejected { background: #fef2f2; color: #991b1b; }
.empty { padding: 2rem; text-align: center; color: #999; font-size: .9rem; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; padding: 1rem; font-size: .85rem; color: #666; }
.pagination button { padding: .3rem .7rem; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; cursor: pointer; }
.pagination button:disabled { opacity: .4; cursor: not-allowed; }
</style>
