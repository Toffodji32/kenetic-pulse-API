<template>
  <div class="admin-page">
    <h1 class="page-title">Gestion des retraits</h1>

    <!-- Stats -->
    <div class="stats" v-if="stats">
      <div class="stat stat-orange">
        <div class="stat-value">{{ stats.pendingWithdrawals.count }}</div>
        <div class="stat-label">En attente</div>
      </div>
      <div class="stat stat-orange">
        <div class="stat-value">{{ formatAmount(stats.pendingWithdrawals.volume) }}</div>
        <div class="stat-label">Volume en attente</div>
      </div>
      <div class="stat stat-green">
        <div class="stat-value">{{ stats.completedThisMonth.count }}</div>
        <div class="stat-label">Validés ce mois</div>
      </div>
      <div class="stat stat-green">
        <div class="stat-value">{{ formatAmount(stats.completedThisMonth.volume) }}</div>
        <div class="stat-label">Volume validé ce mois</div>
      </div>
      <div class="stat stat-blue">
        <div class="stat-value">{{ formatAmount(stats.totalCirculation) }}</div>
        <div class="stat-label">En circulation</div>
      </div>
      <div class="stat stat-purple">
        <div class="stat-value">{{ formatAmount(stats.totalCommissions) }}</div>
        <div class="stat-label">Commissions perçues</div>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="tabs">
      <button v-for="s in statuses" :key="s.value" @click="loadWithdrawals(s.value)" :class="['tab', { active: currentStatus === s.value }]">{{ s.label }}</button>
    </div>

    <!-- Withdrawals table -->
    <div class="card table-card">
      <table v-if="items.length">
        <thead>
          <tr>
            <th>Date</th>
            <th>Salle</th>
            <th>Montant</th>
            <th>Mobile Money</th>
            <th>Opérateur</th>
            <th>Statut</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="w in items" :key="w.id">
            <td>{{ formatDate(w.requestedAt) }}</td>
            <td><strong>{{ w.gym?.name }}</strong></td>
            <td>{{ formatAmount(w.amount) }}</td>
            <td>{{ w.mobileMoneyNumber }}</td>
            <td>{{ (w.mobileMoneyOperator || '').toUpperCase() }}</td>
            <td><span :class="'badge badge-' + w.status">{{ statusLabel(w.status) }}</span></td>
            <td class="text-right" v-if="w.status === 'pending'">
              <button class="btn btn-approve" @click="approve(w)">Approuver</button>
              <button class="btn btn-reject" @click="openReject(w)">Rejeter</button>
            </td>
            <td v-else class="text-right text-muted">—</td>
          </tr>
        </tbody>
      </table>
      <div v-else-if="!loading" class="empty">Aucun retrait {{ currentStatus !== 'all' ? statusLabel(currentStatus) : '' }}.</div>
      <div v-else class="empty">Chargement...</div>

      <div class="pagination" v-if="meta.pages > 1">
        <button @click="loadPage(currentPage - 1)" :disabled="currentPage <= 1">‹</button>
        <span>Page {{ currentPage }} / {{ meta.pages }}</span>
        <button @click="loadPage(currentPage + 1)" :disabled="currentPage >= meta.pages">›</button>
      </div>
    </div>

    <!-- Reject modal -->
    <div v-if="rejectTarget" class="modal-overlay" @click.self="rejectTarget = null">
      <div class="modal card">
        <h2>Rejeter le retrait #{{ rejectTarget.id }}</h2>
        <p><strong>Salle :</strong> {{ rejectTarget.gym?.name }}</p>
        <p><strong>Montant :</strong> {{ formatAmount(rejectTarget.amount) }}</p>

        <div class="form-group">
          <label>Raison du rejet (obligatoire)</label>
          <textarea v-model="rejectReason" rows="3" placeholder="Expliquez pourquoi ce retrait est rejeté..." required></textarea>
        </div>

        <div class="form-actions">
          <button class="btn btn-secondary" @click="rejectTarget = null">Annuler</button>
          <button class="btn btn-reject" @click="confirmReject" :disabled="!rejectReason.trim() || rejecting">
            {{ rejecting ? 'Traitement...' : 'Confirmer le rejet' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Success feedback -->
    <div v-if="feedback" class="toast" :class="'toast-' + feedback.type">
      {{ feedback.message }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useWalletStore } from '@/stores/wallet'

const store = useWalletStore()
const items = ref([])
const meta = ref({ page: 1, total: 0, pages: 1 })
const currentPage = ref(1)
const currentStatus = ref('pending')
const loading = ref(false)
const rejectTarget = ref(null)
const rejectReason = ref('')
const rejecting = ref(false)
const feedback = ref(null)
const stats = ref(null)

const statuses = [
  { value: 'pending', label: 'En attente' },
  { value: 'processing', label: 'En cours' },
  { value: 'completed', label: 'Effectués' },
  { value: 'rejected', label: 'Rejetés' },
  { value: 'all', label: 'Tous' },
]

function formatAmount(v) { return (v || 0).toLocaleString('fr-FR') + ' FCFA' }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—' }
function statusLabel(s) { return { pending: 'En attente', approved: 'Approuvé', processing: 'En cours', completed: 'Effectué', rejected: 'Rejeté' }[s] || s }

function showFeedback(type, message) {
  feedback.value = { type, message }
  setTimeout(() => feedback.value = null, 4000)
}

async function loadWithdrawals(status, page = 1) {
  currentStatus.value = status
  currentPage.value = page
  loading.value = true
  try {
    const data = await store.fetchPendingWithdrawals(page, status === 'all' ? undefined : status)
    items.value = data.data
    meta.value = { page: data.page, total: data.total, pages: data.pages }
  } catch (e) {
    showFeedback('error', 'Erreur de chargement')
  } finally {
    loading.value = false
  }
}

function loadPage(page) {
  loadWithdrawals(currentStatus.value, page)
}

async function approve(w) {
  try {
    await store.approveWithdrawal(w.id)
    showFeedback('success', `Retrait #${w.id} approuvé et envoyé à FedaPay.`)
    loadWithdrawals(currentStatus.value, currentPage.value)
    loadStats()
  } catch (e) {
    showFeedback('error', e.response?.data?.error || 'Erreur lors de l\'approbation')
  }
}

function openReject(w) {
  rejectTarget.value = w
  rejectReason.value = ''
}

async function confirmReject() {
  if (!rejectTarget.value) return
  rejecting.value = true
  try {
    await store.rejectWithdrawal(rejectTarget.value.id, rejectReason.value)
    showFeedback('success', `Retrait #${rejectTarget.value.id} rejeté.`)
    rejectTarget.value = null
    rejectReason.value = ''
    loadWithdrawals(currentStatus.value, currentPage.value)
    loadStats()
  } catch (e) {
    showFeedback('error', e.response?.data?.error || 'Erreur lors du rejet')
  } finally {
    rejecting.value = false
  }
}

async function loadStats() {
  try {
    stats.value = await store.fetchWalletStats()
  } catch (e) {
    // silent
  }
}

onMounted(() => {
  loadWithdrawals('pending')
  loadStats()
})
</script>

<style scoped>
.page-title { margin-bottom: 1.5rem; }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.stat { background: #fff; border-radius: 10px; padding: 1rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.stat-value { font-size: 1.3rem; font-weight: 700; }
.stat-label { font-size: .75rem; color: #6b7280; margin-top: .25rem; }
.stat-orange .stat-value { color: #ea580c; }
.stat-green .stat-value { color: #059669; }
.stat-blue .stat-value { color: #2563eb; }
.stat-purple .stat-value { color: #7c3aed; }
.tabs { display: flex; gap: .5rem; margin-bottom: 1rem; }
.tab { padding: .4rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; cursor: pointer; font-size: .85rem; }
.tab.active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
.table-card { overflow-x: auto; padding: 0; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: .75rem 1rem; text-align: left; font-size: .85rem; border-bottom: 1px solid #f3f4f6; }
th { font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: .75rem; background: #f9fafb; }
.text-right { text-align: right; }
.text-muted { color: #d1d5db; }
.badge { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 500; }
.badge-pending, .badge-processing { background: #fff7ed; color: #9a3412; }
.badge-approved { background: #eff6ff; color: #1e40af; }
.badge-completed { background: #ecfdf5; color: #065f46; }
.badge-rejected { background: #fef2f2; color: #991b1b; }
.btn { padding: .4rem .8rem; border: none; border-radius: 6px; cursor: pointer; font-size: .8rem; font-weight: 500; margin: 0 .2rem; }
.btn-approve { background: #ecfdf5; color: #065f46; }
.btn-reject { background: #fef2f2; color: #991b1b; }
.btn-secondary { background: #e5e7eb; color: #333; }
.empty { padding: 2rem; text-align: center; color: #999; font-size: .9rem; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; padding: 1rem; font-size: .85rem; color: #666; }
.pagination button { padding: .3rem .7rem; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; cursor: pointer; }
.pagination button:disabled { opacity: .4; cursor: not-allowed; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal { width: 100%; max-width: 480px; padding: 2rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .25rem; }
.form-group textarea { width: 100%; padding: .6rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: .9rem; resize: vertical; }
.form-actions { display: flex; gap: .5rem; justify-content: flex-end; margin-top: 1rem; }
.toast { position: fixed; top: 1rem; right: 1rem; padding: .75rem 1.25rem; border-radius: 8px; font-size: .9rem; z-index: 200; box-shadow: 0 4px 12px rgba(0,0,0,.15); }
.toast-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.toast-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>
