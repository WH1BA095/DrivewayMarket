import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import Constants from 'expo-constants';

// ── конфиг ────────────────────────────────────────────────────────────────
function getServerBase() {
  //if (!__DEV__) return 'http://176.98.176.149:8899';
  if (true) return 'http://176.98.176.149:8899';
  // На реальном устройстве берём IP Metro-сервера автоматически
  const metroHost = Constants.expoConfig?.hostUri?.split(':')[0];
  if (metroHost) return `http://${metroHost}:8899`;
  // Фолбэк для эмуляторов
  return Platform.OS === 'android'
    ? 'http://10.0.2.2:8899'
    : 'http://localhost:8899';
}

export const SERVER_BASE = getServerBase();
const API_URL = `${SERVER_BASE}/api/app.php`;

// ── утилиты ───────────────────────────────────────────────────────────────
export const getImageUrl = (path) => {
  if (!path) return null;
  if (path.startsWith('http')) return path;
  return `${SERVER_BASE}/${path}`;
};

const getToken = () => SecureStore.getItemAsync('auth_token');

const qs = (params) => {
  const parts = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
  return parts.length ? '?' + parts.join('&') : '';
};

async function apiFetch(action, { method = 'GET', params = {}, body = null } = {}) {
  try {
    const token  = await getToken();
    const query  = qs({ action, ...(method === 'GET' ? params : {}) });
    const url    = `${API_URL}${query}`;
    const headers = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const opts = { method, headers };
    if (method === 'POST' && body) opts.body = JSON.stringify(body);

    const res  = await fetch(url, opts);
    const data = await res.json();
    return data;
  } catch (e) {
    // Сетевая ошибка или недоступен сервер
    return { success: false, message: 'Ошибка сети. Проверьте подключение к интернету.' };
  }
}

// ── endpoints ─────────────────────────────────────────────────────────────
export const api = {
  // public
  categories:     ()       => apiFetch('categories'),
  products:       (p = {}) => apiFetch('products', { params: p }),
  product:        (id)     => apiFetch('product',  { params: { id } }),
  brands:         ()       => apiFetch('brands'),
  models:         (bid)    => apiFetch('models',   { params: { brand_id: bid } }),

  // auth
  login:          (email, password) => apiFetch('login',    { method: 'POST', body: { email, password } }),
  register:       (data)            => apiFetch('register', { method: 'POST', body: data }),
  logout:         ()                => apiFetch('logout',   { method: 'POST' }),

  // protected
  profile:        ()     => apiFetch('profile'),
  updateProfile:  (data) => apiFetch('update_profile',  { method: 'POST', body: data }),
  changePassword: (data) => apiFetch('change_password', { method: 'POST', body: data }),

  favorites:      ()     => apiFetch('favorites'),
  toggleFavorite: (pid)  => apiFetch('toggle_favorite', { method: 'POST', body: { product_id: pid } }),

  userCars:       ()     => apiFetch('user_cars'),
  addCar:         (data) => apiFetch('add_car',    { method: 'POST', body: data }),
  deleteCar:      (id)   => apiFetch('delete_car', { method: 'POST', body: { car_id: id } }),

  myReviews:      ()     => apiFetch('my_reviews'),
  addReview:      (data) => apiFetch('add_review', { method: 'POST', body: data }),

  myQuestions:    ()     => apiFetch('my_questions'),
  getQuestions:   (pid)  => apiFetch('get_questions', { params: { product_id: pid } }),
  addQuestion:    (data) => apiFetch('add_question',  { method: 'POST', body: data }),

  sendSupport:    (data) => apiFetch('send_support', { method: 'POST', body: data }),
  mySupport:      ()     => apiFetch('my_support'),

  myOrders:       ()     => apiFetch('my_orders'),
  orderDetail:    (id)   => apiFetch('order_detail', { params: { id } }),
  placeOrder:     (data) => apiFetch('place_order',  { method: 'POST', body: data }),
  cancelOrder:    (id)   => apiFetch('cancel_order', { method: 'POST', body: { order_id: id } }),

  getCart:        ()       => apiFetch('get_cart'),
  syncCart:       (items)  => apiFetch('sync_cart',  { method: 'POST', body: { items } }),
  clearCartSync:  ()       => apiFetch('clear_cart', { method: 'POST' }),

  uploadAvatar: async (imageUri) => {
    const token = await getToken();
    const url   = `${API_URL}?action=upload_avatar`;
    const formData = new FormData();
    const filename = imageUri.split('/').pop();
    const ext      = filename.split('.').pop().toLowerCase();
    const mime     = ext === 'png' ? 'image/png' : 'image/jpeg';
    formData.append('avatar', { uri: imageUri, name: filename, type: mime });
    const headers = {};
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const res  = await fetch(url, { method: 'POST', headers, body: formData });
    const data = await res.json();
    return data;
  },
};
