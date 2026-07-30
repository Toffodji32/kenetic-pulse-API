<template>
  <div class="shop-page">
    <header class="shop-header">
      <h1>Boutique {{ gymName }}</h1>
      <button class="btn btn-cart" @click="showCart = !showCart">
        Panier ({{ cartTotalItems }})
      </button>
    </header>

    <div v-if="!gymSlug" class="alert alert-error">
      Impossible de déterminer votre salle de sport. <button class="btn btn-sm" @click="refresh">Reconnectez-vous</button>
    </div>

    <div v-else-if="loading" class="empty">Chargement des produits...</div>

    <div v-else-if="loadError" class="alert alert-error">{{ loadError }}</div>

    <!-- Product grid -->
    <div class="product-grid" v-else-if="products.length">
      <div v-for="p in products" :key="p.id" class="product-card card">
        <div class="product-img" v-if="p.image">
          <img :src="p.image" :alt="p.name" />
        </div>
        <div class="product-body">
          <h3>{{ p.name }}</h3>
          <p class="product-desc">{{ p.description }}</p>
          <p class="product-price">{{ formatPrice(p.price) }}</p>
          <p class="product-stock" :class="'stock-' + p.stock_status">
            {{ p.stock_status === 'out_of_stock' ? 'Rupture' : p.quantity + ' en stock' }}
          </p>
          <button
            class="btn btn-primary btn-sm"
            :disabled="p.stock_status === 'out_of_stock'"
            @click="addToCart(p)"
          >
            Ajouter au panier
          </button>
        </div>
      </div>
    </div>
    <div v-else class="empty">Aucun produit disponible pour le moment dans cette salle.</div>

    <!-- Cart drawer -->
    <div v-if="showCart" class="modal-overlay" @click.self="showCart = false">
      <div class="modal cart-drawer card">
        <h2>Votre panier</h2>
        <div v-if="!cart.length" class="empty">Panier vide</div>
        <div v-else>
          <div v-for="(item, i) in cart" :key="i" class="cart-item">
            <div class="cart-item-info">
              <strong>{{ item.name }}</strong>
              <span>{{ formatPrice(item.price) }} x {{ item.quantity }}</span>
            </div>
            <div class="cart-item-actions">
              <span class="cart-item-total">{{ formatPrice(item.price * item.quantity) }}</span>
              <button class="btn btn-sm btn-danger" @click="removeFromCart(i)">×</button>
            </div>
          </div>
          <div class="cart-total">
            <strong>Total : {{ formatPrice(cartTotal) }}</strong>
          </div>
        </div>

        <div v-if="cart.length && !orderSuccess">
          <h3 style="margin-top:1rem">Finaliser la commande</h3>
          <form @submit.prevent="submitOrder">
            <div class="form-group">
              <label>Type de livraison</label>
              <select v-model="deliveryType" required>
                <option value="retrait">Retrait sur place</option>
                <option value="livraison">Livraison</option>
              </select>
            </div>
            <div class="form-group" v-if="deliveryType === 'livraison'">
              <label>Adresse de livraison</label>
              <input v-model="deliveryAddress" required placeholder="Votre adresse" />
            </div>
            <button type="submit" class="btn btn-primary btn-block" :disabled="orderLoading">
              {{ orderLoading ? 'Commande en cours...' : 'Commander' }}
            </button>
            <div v-if="orderError" class="alert alert-error">{{ orderError }}</div>
          </form>
        </div>

        <div v-if="orderSuccess" class="alert alert-success">
          Commande passée avec succès !
          <button @click="resetOrder" class="btn btn-sm">Continuer mes achats</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { API } from '@/stores/auth'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const gymSlug = computed(() => auth.user?.gym_slug)
const gymName = computed(() => auth.user?.gym_name || 'Boutique')

const products = ref([])
const cart = ref([])
const showCart = ref(false)
const deliveryType = ref('retrait')
const deliveryAddress = ref('')
const orderLoading = ref(false)
const orderError = ref('')
const orderSuccess = ref(false)
const loading = ref(true)
const loadError = ref('')

const cartTotalItems = computed(() => cart.value.reduce((s, i) => s + i.quantity, 0))
const cartTotal = computed(() => cart.value.reduce((s, i) => s + i.price * i.quantity, 0))

function formatPrice(v) { return (v || 0).toLocaleString('fr-FR') + ' FCFA' }

async function fetchProducts() {
  if (!gymSlug.value) {
    loading.value = false
    loadError.value = 'Aucune salle de sport associée à votre compte.'
    return
  }
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await API.get(`/api/shop/${gymSlug.value}/products`)
    products.value = data
  } catch (e) {
    loadError.value = 'Erreur lors du chargement des produits : ' + (e.response?.data?.error || e.message)
  } finally {
    loading.value = false
  }
}

function refresh() {
  auth.logout()
  window.location.href = '/login'
}

function addToCart(p) {
  const existing = cart.value.find(i => i.id === p.id)
  if (existing) {
    existing.quantity++
  } else {
    cart.value.push({ id: p.id, name: p.name, price: p.price, quantity: 1 })
  }
}

function removeFromCart(i) {
  cart.value.splice(i, 1)
}

function resetOrder() {
  cart.value = []
  orderSuccess.value = false
  showCart.value = false
  deliveryType.value = 'retrait'
  deliveryAddress.value = ''
}

async function submitOrder() {
  orderLoading.value = true
  orderError.value = ''
  try {
    const items = cart.value.map(i => ({ product_id: i.id, quantity: i.quantity }))
    await API.post(`/api/shop/${gymSlug.value}/orders`, {
      items,
      delivery_type: deliveryType.value,
      delivery_address: deliveryType.value === 'livraison' ? deliveryAddress.value : null,
    })
    orderSuccess.value = true
  } catch (e) {
    orderError.value = e.response?.data?.error || 'Erreur lors de la commande'
  } finally {
    orderLoading.value = false
  }
}

onMounted(async () => {
  if (!gymSlug.value) {
    await auth.refreshUser()
  }
  await fetchProducts()
})
</script>

<style scoped>
.shop-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
.shop-header h1 { font-size: 1.5rem; }
.btn-cart { padding: .6rem 1.2rem; background: #4f46e5; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; }
.product-card { overflow: hidden; }
.product-img { width: 100%; height: 180px; overflow: hidden; background: #f3f4f6; }
.product-img img { width: 100%; height: 100%; object-fit: cover; }
.product-body { padding: 1rem; }
.product-body h3 { font-size: 1rem; margin-bottom: .25rem; }
.product-desc { font-size: .8rem; color: #6b7280; margin-bottom: .5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.product-price { font-size: 1.1rem; font-weight: 700; color: #4f46e5; margin-bottom: .25rem; }
.product-stock { font-size: .75rem; margin-bottom: .5rem; }
.stock-in_stock { color: #059669; }
.stock-low { color: #d97706; }
.stock-out_of_stock { color: #dc2626; }
.cart-drawer { max-width: 500px; max-height: 80vh; overflow-y: auto; }
.cart-item { display: flex; align-items: center; justify-content: space-between; padding: .5rem 0; border-bottom: 1px solid #f3f4f6; }
.cart-item-info { display: flex; flex-direction: column; gap: .1rem; }
.cart-item-info strong { font-size: .9rem; }
.cart-item-info span { font-size: .8rem; color: #6b7280; }
.cart-item-actions { display: flex; align-items: center; gap: .5rem; }
.cart-item-total { font-weight: 600; }
.cart-total { text-align: right; padding: .75rem 0; font-size: 1.1rem; }
.btn-danger { background: #ef4444; color: #fff; border: none; border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.alert { padding: .75rem 1rem; border-radius: 8px; font-size: .9rem; }
.alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>
