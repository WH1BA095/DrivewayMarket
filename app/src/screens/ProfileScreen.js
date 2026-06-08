import React, { useState, useCallback } from 'react';
import {
  View, Text, ScrollView, TouchableOpacity, StyleSheet,
  ActivityIndicator, Alert, TextInput, Modal, RefreshControl, Image, Switch,
} from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useFocusEffect } from '@react-navigation/native';
import { api, getImageUrl } from '../api';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import { useTheme } from '../context/ThemeContext';
import { SIZES } from '../constants/theme';
import StarRating from '../components/StarRating';

const TABS = [
  { key: 'orders',    label: 'Заказы',    icon: 'receipt-outline' },
  { key: 'favorites', label: 'Избранное', icon: 'heart-outline' },
  { key: 'reviews',   label: 'Отзывы',    icon: 'star-outline' },
  { key: 'questions', label: 'Вопросы',   icon: 'chatbubble-outline' },
  { key: 'support',   label: 'Поддержка', icon: 'headset-outline' },
  { key: 'settings',  label: 'Настройки', icon: 'settings-outline' },
];

export default function ProfileScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { user, logout, updateUser } = useAuth();
  const { clearCart } = useCart();
  const { colors, shadows, isDark, toggleTheme, setTheme } = useTheme();

  const [tab,        setTab]       = useState('orders');
  const [reviews,    setReviews]   = useState([]);
  const [questions,  setQuestions] = useState([]);
  const [orders,     setOrders]    = useState([]);
  const [loading,    setLoading]   = useState(true);
  const [refreshing, setRefreshing]= useState(false);
  const [support,     setSupport]    = useState([]);
  const [supModal,    setSupModal]   = useState(false);
  const [supSubject,  setSupSubject] = useState('');
  const [supMessage,  setSupMessage] = useState('');
  const [supSending,  setSupSending] = useState(false);
  const [editModal,   setEditModal]  = useState(false);
  const [passModal,   setPassModal]  = useState(false);
  const [avatarLoading, setAvatarLoading] = useState(false);
  const [editForm,   setEditForm]  = useState({ firstname:'', lastname:'', phone:'', address:'' });
  const [editSaving, setEditSaving]= useState(false);
  const [passForm,   setPassForm]  = useState({ current:'', new1:'', new2:'' });
  const [passSaving, setPassSaving]= useState(false);

  const load = useCallback(async () => {
    if (!user) { setLoading(false); return; }
    try {
      const [rRes, qRes, oRes, sRes] = await Promise.all([
        api.myReviews(), api.myQuestions(), api.myOrders(), api.mySupport(),
      ]);
      if (rRes.success) setReviews(rRes.reviews);
      if (qRes.success) setQuestions(qRes.questions);
      if (oRes.success) setOrders(oRes.orders);
      if (sRes.success) setSupport(sRes.messages);
    } catch (e) {
      // Сетевая ошибка — продолжаем с пустыми данными
    } finally {
      setLoading(false); setRefreshing(false);
    }
  }, [user]);

  useFocusEffect(useCallback(() => { setLoading(true); load(); }, [load]));

  const openEdit = () => {
    setEditForm({ firstname: user.firstname||'', lastname: user.lastname||'', phone: user.phone||'', address: user.address||'' });
    setEditModal(true);
  };

  const saveProfile = async () => {
    if (!editForm.firstname.trim()) { Alert.alert('Ошибка','Имя обязательно'); return; }
    setEditSaving(true);
    const res = await api.updateProfile(editForm);
    setEditSaving(false);
    if (res.success) {
      updateUser({ ...editForm, full_name: `${editForm.firstname} ${editForm.lastname}`.trim() });
      setEditModal(false);
    } else Alert.alert('Ошибка', res.message);
  };

  const savePassword = async () => {
    if (!passForm.current || !passForm.new1) { Alert.alert('Ошибка','Заполните все поля'); return; }
    if (passForm.new1 !== passForm.new2)     { Alert.alert('Ошибка','Пароли не совпадают'); return; }
    setPassSaving(true);
    const res = await api.changePassword({ current_password: passForm.current, new_password: passForm.new1, new_password2: passForm.new2 });
    setPassSaving(false);
    if (res.success) { setPassModal(false); setPassForm({current:'',new1:'',new2:''}); Alert.alert('Готово','Пароль изменён'); }
    else Alert.alert('Ошибка', res.message);
  };

  const pickAvatar = async () => {
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (status !== 'granted') {
      Alert.alert('Нет доступа', 'Разрешите доступ к галерее в настройках телефона');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.8,
    });
    if (result.canceled) return;
    const uri = result.assets[0].uri;
    setAvatarLoading(true);
    try {
      const res = await api.uploadAvatar(uri);
      if (res.success) {
        updateUser({ avatar: res.avatar });
      } else {
        Alert.alert('Ошибка', res.message || 'Не удалось загрузить фото');
      }
    } catch (e) {
      Alert.alert('Ошибка', 'Не удалось загрузить фото');
    } finally {
      setAvatarLoading(false);
    }
  };

  const handleLogout = () => Alert.alert('Выход', 'Вы уверены?', [
    { text: 'Отмена', style: 'cancel' },
    {
      text: 'Выйти', style: 'destructive',
      onPress: async () => { await logout(); clearCart(); },
    },
  ]);

  // ── Not logged in ──
  if (!user) {
    return (
      <View style={[styles.container, { backgroundColor: colors.background, paddingTop: insets.top }]}>
        <View style={[styles.guestCard, { backgroundColor: colors.card, ...shadows.sm }]}>
          <View style={[styles.guestAvatar, { backgroundColor: colors.primaryLight }]}>
            <Ionicons name="person-outline" size={48} color={colors.primary} />
          </View>
          <Text style={[styles.guestTitle, { color: colors.text }]}>Личный кабинет</Text>
          <Text style={[styles.guestSub, { color: colors.textSecondary }]}>
            Войдите для доступа к заказам, избранному и отзывам
          </Text>
          <TouchableOpacity style={[styles.loginBtn, { backgroundColor: colors.primary, ...shadows.md }]} onPress={() => navigation.navigate('Login')}>
            <Text style={styles.loginBtnText}>Войти</Text>
          </TouchableOpacity>
          <TouchableOpacity style={[styles.regBtn, { borderColor: colors.border }]} onPress={() => navigation.navigate('Register')}>
            <Text style={[styles.regBtnText, { color: colors.text }]}>Зарегистрироваться</Text>
          </TouchableOpacity>
        </View>

        {/* Theme toggle even for guests */}
        <View style={[styles.themeCard, { backgroundColor: colors.card, ...shadows.sm }]}>
          <Ionicons name={isDark ? 'moon-outline' : 'sunny-outline'} size={20} color={colors.primary} />
          <Text style={[styles.themeLabel, { color: colors.text }]}>Тёмная тема</Text>
          <Switch value={isDark} onValueChange={toggleTheme} trackColor={{ true: colors.primary }} thumbColor="#fff" />
        </View>
      </View>
    );
  }

  const avatarUri = getImageUrl(user.avatar);

  // ── Tab content ──
  const renderTabContent = () => {
    if (loading) return <ActivityIndicator color={colors.primary} style={{ marginTop: 40 }} />;

    switch (tab) {
      case 'orders':
        if (orders.length === 0) return <EmptyState icon="receipt-outline" text="Заказов пока нет" colors={colors} onAction={() => navigation.navigate('CatalogTab')} actionText="В каталог" />;
        return orders.slice(0, 5).map(o => (
          <TouchableOpacity key={o.id}
            style={[styles.listCard, { backgroundColor: colors.card, ...shadows.sm }]}
            onPress={() => navigation.navigate('Orders')}
          >
            <Text style={[styles.listCardTitle, { color: colors.text }]}>Заказ №{o.user_order_number ?? o.id}</Text>
            <Text style={[styles.listCardSub, { color: colors.textSecondary }]}>
              {new Date(o.created_at).toLocaleDateString('ru-RU')} · {o.item_count} тов.
            </Text>
            <Text style={[styles.listCardPrice, { color: colors.primary }]}>{Number(o.total).toLocaleString('ru-RU')} ₽</Text>
          </TouchableOpacity>
        )).concat(orders.length > 5 ? [
          <TouchableOpacity key="all" style={[styles.seeAllBtn, { borderColor: colors.border }]} onPress={() => navigation.navigate('Orders')}>
            <Text style={[styles.seeAllText, { color: colors.primary }]}>Все заказы ({orders.length})</Text>
            <Ionicons name="chevron-forward" size={16} color={colors.primary} />
          </TouchableOpacity>
        ] : []);

      case 'favorites':
        return (
          <TouchableOpacity style={[styles.navItem, { backgroundColor: colors.card, ...shadows.sm }]} onPress={() => navigation.navigate('FavoritesTab')}>
            <Ionicons name="heart-outline" size={22} color={colors.primary} />
            <Text style={[styles.navItemText, { color: colors.text }]}>Открыть избранное</Text>
            <Ionicons name="chevron-forward" size={18} color={colors.textTertiary} />
          </TouchableOpacity>
        );

      case 'reviews':
        if (reviews.length === 0) return <EmptyState icon="star-outline" text="Вы ещё не оставляли отзывов" colors={colors} />;
        return reviews.map(r => (
          <View key={r.id} style={[styles.listCard, { backgroundColor: colors.card, ...shadows.sm }]}>
            <Text style={[styles.listCardTitle, { color: colors.text }]} numberOfLines={1}>{r.product_name}</Text>
            <StarRating rating={Number(r.rating)} showNumber={false} size={13} />
            {r.title ? <Text style={[styles.reviewTitle, { color: colors.text }]}>{r.title}</Text> : null}
            <Text style={[styles.listCardSub, { color: colors.textSecondary }]} numberOfLines={2}>{r.body}</Text>
            {r.admin_reply && (
              <View style={[styles.adminReply, { backgroundColor: colors.primaryLight }]}>
                <Text style={[styles.adminReplyLabel, { color: colors.primary }]}>Ответ магазина</Text>
                <Text style={[styles.listCardSub, { color: colors.text }]}>{r.admin_reply}</Text>
              </View>
            )}
          </View>
        ));

      case 'questions':
        if (questions.length === 0) return <EmptyState icon="chatbubble-outline" text="Вопросов пока нет" colors={colors} />;
        return questions.map(q => (
          <View key={q.id} style={[styles.listCard, { backgroundColor: colors.card, ...shadows.sm, gap: 8 }]}>
            <Text style={[styles.listCardTitle, { color: colors.text }]} numberOfLines={1}>{q.product_name}</Text>
            <View style={[styles.qBubble, { backgroundColor: colors.inputBg }]}>
              <Text style={[styles.qLabel, { color: colors.textTertiary }]}>Ваш вопрос</Text>
              <Text style={[styles.listCardSub, { color: colors.text }]}>{q.question}</Text>
            </View>
            {q.answer ? (
              <View style={[styles.qBubble, { backgroundColor: colors.primaryLight }]}>
                <Text style={[styles.qLabel, { color: colors.primary }]}>Ответ магазина</Text>
                <Text style={[styles.listCardSub, { color: colors.text }]}>{q.answer}</Text>
              </View>
            ) : (
              <Text style={[styles.pendingText, { color: colors.warning }]}>⏳ Ожидает ответа</Text>
            )}
          </View>
        ));

      case 'cars':
        return (
          <TouchableOpacity style={[styles.navItem, { backgroundColor: colors.card, ...shadows.sm }]} onPress={() => navigation.navigate('Cars')}>
            <Ionicons name="car-sport-outline" size={22} color={colors.primary} />
            <Text style={[styles.navItemText, { color: colors.text }]}>Управление автомобилями</Text>
            <Ionicons name="chevron-forward" size={18} color={colors.textTertiary} />
          </TouchableOpacity>
        );

      case 'support': {
        const STATUS_COLORS = { new: colors.warning, read: colors.textSecondary, replied: '#22c55e' };
        const STATUS_LABELS = { new: 'Новое', read: 'Прочитано', replied: 'Отвечено' };
        return (
          <View>
            <TouchableOpacity
              style={[styles.newSupportBtn, { backgroundColor: colors.primary, ...shadows.md }]}
              onPress={() => { setSupSubject(''); setSupMessage(''); setSupModal(true); }}
            >
              <Ionicons name="create-outline" size={18} color="#fff" />
              <Text style={styles.newSupportBtnText}>Новое обращение</Text>
            </TouchableOpacity>
            {support.length === 0
              ? <EmptyState icon="headset-outline" text="Обращений ещё нет" colors={colors} />
              : support.map(m => (
                <View key={m.id} style={[styles.listCard, { backgroundColor: colors.card, ...shadows.sm }]}>
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                    <View style={{ flex: 1, marginRight: 8 }}>
                      {m.subject ? <Text style={[styles.listCardTitle, { color: colors.text }]} numberOfLines={1}>{m.subject}</Text> : null}
                      <Text style={[styles.listCardSub, { color: colors.textSecondary, marginTop: 2 }]}>
                        {new Date(m.created_at).toLocaleDateString('ru-RU', { day: '2-digit', month: 'long' })}
                      </Text>
                    </View>
                    <View style={[styles.supStatusBadge, { backgroundColor: (STATUS_COLORS[m.status]||colors.textSecondary)+'22' }]}>
                      <Text style={[styles.supStatusText, { color: STATUS_COLORS[m.status]||colors.textSecondary }]}>
                        {STATUS_LABELS[m.status]||m.status}
                      </Text>
                    </View>
                  </View>
                  <Text style={[styles.listCardSub, { color: colors.text, marginTop: 8 }]} numberOfLines={3}>{m.message}</Text>
                  {m.reply ? (
                    <View style={[styles.adminReply, { backgroundColor: colors.primaryLight, marginTop: 8 }]}>
                      <Text style={[styles.adminReplyLabel, { color: colors.primary }]}>Ответ магазина</Text>
                      <Text style={[styles.listCardSub, { color: colors.text }]}>{m.reply}</Text>
                    </View>
                  ) : null}
                </View>
              ))
            }
          </View>
        );
      }

      case 'settings':
        return (
          <View style={[styles.settingsList, { backgroundColor: colors.card, ...shadows.sm }]}>
            <SettingsRow icon="pencil-outline"      label="Редактировать профиль" onPress={openEdit} colors={colors} />
            <SettingsRow icon="lock-closed-outline" label="Сменить пароль"        onPress={() => setPassModal(true)} colors={colors} />
            <SettingsRow icon="car-sport-outline"   label="Мои автомобили"        onPress={() => navigation.navigate('Cars')} colors={colors} />
            {/* Dark mode toggle */}
            <View style={[styles.settingsRow, { borderBottomColor: colors.borderLight }]}>
              <View style={[styles.settingsIcon, { backgroundColor: colors.primaryLight }]}>
                <Ionicons name={isDark ? 'moon-outline' : 'sunny-outline'} size={18} color={colors.primary} />
              </View>
              <Text style={[styles.settingsLabel, { color: colors.text }]}>Тёмная тема</Text>
              <Switch value={isDark} onValueChange={toggleTheme} trackColor={{ true: colors.primary }} thumbColor="#fff" />
            </View>
            <SettingsRow icon="log-out-outline" label="Выйти" onPress={handleLogout} colors={colors} danger />
          </View>
        );

      default: return null;
    }
  };

  return (
    <View style={[styles.container, { backgroundColor: colors.surface, paddingTop: insets.top }]}>
      <ScrollView
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={colors.primary} />}
      >
        {/* Profile card — скруглённая карточка с красной рамкой, как шапка */}
        <View style={[styles.profileCardOuter, { backgroundColor: colors.surface }]}>
          <View style={[styles.profileCard, {
            backgroundColor:  isDark ? '#000' : '#fff',
            borderColor:      colors.primary,
          }]}>
            <View style={styles.profileCardInner}>
              <TouchableOpacity style={styles.avatarWrap} onPress={pickAvatar} activeOpacity={0.8}>
                {avatarUri
                  ? <Image source={{ uri: avatarUri }} style={[styles.avatar, { borderColor: colors.primary }]} />
                  : (
                    <View style={[styles.avatar, styles.avatarFallback, { backgroundColor: colors.primaryLight }]}>
                      <Text style={[styles.avatarLetter, { color: colors.primary }]}>
                        {(user.firstname||'?')[0].toUpperCase()}
                      </Text>
                    </View>
                  )
                }
                {/* Camera overlay */}
                <View style={[styles.avatarCameraBtn, { backgroundColor: colors.primary }]}>
                  {avatarLoading
                    ? <ActivityIndicator size={10} color="#fff" />
                    : <Ionicons name="camera" size={12} color="#fff" />
                  }
                </View>
              </TouchableOpacity>
              <View style={styles.profileInfo}>
                <Text style={[styles.profileName, { color: colors.text }]}>{user.firstname} {user.lastname}</Text>
                <Text style={[styles.profileEmail, { color: colors.textSecondary }]}>{user.email}</Text>
                {user.phone ? <Text style={[styles.profilePhone, { color: colors.textSecondary }]}>{user.phone}</Text> : null}
              </View>
              <TouchableOpacity style={[styles.editBtn, { backgroundColor: colors.primaryLight }]} onPress={openEdit}>
                <Ionicons name="pencil-outline" size={18} color={colors.primary} />
              </TouchableOpacity>
            </View>

            {/* Stats */}
            <View style={[styles.statsRow, { borderTopColor: colors.border }]}>
              <Stat label="Заказов"  value={orders.length}   colors={colors} />
              <Stat label="Отзывов"  value={reviews.length}  colors={colors} />
              <Stat label="Вопросов" value={questions.length} colors={colors} />
            </View>
          </View>
        </View>

        {/* Tabs — сетка 3×2 */}
        <View style={[styles.tabGrid, { paddingHorizontal: SIZES.screenPadding, paddingVertical: 12 }]}>
          {TABS.map(t => {
            const active = tab === t.key;
            return (
              <TouchableOpacity
                key={t.key}
                style={[styles.tabGridItem, {
                  backgroundColor: active ? colors.primaryLight : colors.card,
                  borderColor: active ? colors.primary : colors.border,
                }]}
                onPress={() => setTab(t.key)}
                activeOpacity={0.75}
              >
                <Ionicons name={t.icon} size={22} color={active ? colors.primary : colors.textSecondary} />
                <Text style={[styles.tabGridLabel, { color: active ? colors.primary : colors.textSecondary }]}>
                  {t.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>

        {/* Content */}
        <View style={styles.tabContent}>
          {renderTabContent()}
        </View>

        <View style={{ height: 40 }} />
      </ScrollView>

      {/* Edit modal */}
      <Modal visible={editModal} animationType="slide" presentationStyle="pageSheet">
        <View style={[styles.modal, { backgroundColor: colors.background }]}>
          <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>Редактировать профиль</Text>
            <TouchableOpacity onPress={() => setEditModal(false)}>
              <Ionicons name="close" size={24} color={colors.text} />
            </TouchableOpacity>
          </View>
          <ScrollView contentContainerStyle={{ padding: SIZES.screenPadding, gap: 14 }}>
            {[
              { label:'Имя',     key:'firstname', ph:'Иван'   },
              { label:'Фамилия', key:'lastname',  ph:'Иванов' },
              { label:'Телефон', key:'phone',     ph:'+7...'  },
              { label:'Адрес',   key:'address',   ph:'г. Москва...' },
            ].map(f => (
              <View key={f.key} style={styles.fieldWrap}>
                <Text style={[styles.fieldLabel, { color: colors.textSecondary }]}>{f.label}</Text>
                <TextInput
                  style={[styles.input, { backgroundColor: colors.inputBg, borderColor: colors.border, color: colors.text }]}
                  value={editForm[f.key]}
                  onChangeText={v => setEditForm(p => ({ ...p, [f.key]: v }))}
                  placeholder={f.ph} placeholderTextColor={colors.textTertiary}
                />
              </View>
            ))}
            <TouchableOpacity style={[styles.saveBtn, { backgroundColor: colors.primary }, editSaving && { opacity:0.7 }]} onPress={saveProfile} disabled={editSaving}>
              {editSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.saveBtnText}>Сохранить</Text>}
            </TouchableOpacity>
          </ScrollView>
        </View>
      </Modal>

      {/* Password modal */}
      <Modal visible={passModal} animationType="slide" presentationStyle="pageSheet">
        <View style={[styles.modal, { backgroundColor: colors.background }]}>
          <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>Сменить пароль</Text>
            <TouchableOpacity onPress={() => setPassModal(false)}>
              <Ionicons name="close" size={24} color={colors.text} />
            </TouchableOpacity>
          </View>
          <View style={{ padding: SIZES.screenPadding, gap: 14 }}>
            {[
              { label:'Текущий пароль', key:'current' },
              { label:'Новый пароль',   key:'new1'    },
              { label:'Повторите',      key:'new2'    },
            ].map(f => (
              <View key={f.key} style={styles.fieldWrap}>
                <Text style={[styles.fieldLabel, { color: colors.textSecondary }]}>{f.label}</Text>
                <TextInput
                  style={[styles.input, { backgroundColor: colors.inputBg, borderColor: colors.border, color: colors.text }]}
                  value={passForm[f.key]}
                  onChangeText={v => setPassForm(p => ({ ...p, [f.key]: v }))}
                  secureTextEntry placeholder="••••••" placeholderTextColor={colors.textTertiary}
                />
              </View>
            ))}
            <TouchableOpacity style={[styles.saveBtn, { backgroundColor: colors.primary }, passSaving && { opacity:0.7 }]} onPress={savePassword} disabled={passSaving}>
              {passSaving ? <ActivityIndicator color="#fff" /> : <Text style={styles.saveBtnText}>Изменить пароль</Text>}
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* ── Support Modal ── */}
      <Modal visible={supModal} animationType="slide" presentationStyle="pageSheet">
        <View style={[styles.modalContainer, { backgroundColor: colors.background }]}>
          <View style={[styles.modalHeader, { borderBottomColor: colors.borderLight }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>Новое обращение</Text>
            <TouchableOpacity onPress={() => setSupModal(false)}>
              <Ionicons name="close" size={24} color={colors.text} />
            </TouchableOpacity>
          </View>
          <ScrollView contentContainerStyle={styles.modalBody} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
            <Text style={[styles.fieldLabel, { color: colors.textSecondary }]}>Тема</Text>
            <TextInput
              style={[styles.input, { backgroundColor: colors.inputBg, borderColor: colors.border, color: colors.text }]}
              placeholder="Вопрос по заказу, возврат, другое..."
              placeholderTextColor={colors.textTertiary}
              value={supSubject}
              onChangeText={setSupSubject}
              returnKeyType="next"
            />
            <Text style={[styles.fieldLabel, { color: colors.textSecondary }]}>Сообщение *</Text>
            <TextInput
              style={[styles.input, styles.textarea, { backgroundColor: colors.inputBg, borderColor: colors.border, color: colors.text }]}
              placeholder="Опишите ваш вопрос подробнее..."
              placeholderTextColor={colors.textTertiary}
              value={supMessage}
              onChangeText={setSupMessage}
              multiline
              textAlignVertical="top"
            />
            <Text style={[styles.listCardSub, { color: colors.textSecondary, marginTop: 6 }]}>
              Ответ придёт в этот раздел. Обычно отвечаем в течение суток.
            </Text>
            <TouchableOpacity
              style={[styles.saveBtn, { backgroundColor: colors.primary, opacity: supSending ? 0.7 : 1 }]}
              onPress={async () => {
                if (!supMessage.trim()) { Alert.alert('Ошибка', 'Введите сообщение'); return; }
                setSupSending(true);
                const res = await api.sendSupport({
                  name:    user.firstname + ' ' + (user.lastname || ''),
                  email:   user.email,
                  subject: supSubject.trim(),
                  message: supMessage.trim(),
                });
                setSupSending(false);
                if (res.success) {
                  setSupModal(false);
                  Alert.alert('Отправлено', 'Обращение принято. Ответим в ближайшее время!');
                  const sRes = await api.mySupport();
                  if (sRes.success) setSupport(sRes.messages);
                } else {
                  Alert.alert('Ошибка', res.message || 'Попробуйте ещё раз');
                }
              }}
              disabled={supSending}
            >
              {supSending
                ? <ActivityIndicator color="#fff" />
                : <Text style={styles.saveBtnText}>Отправить</Text>
              }
            </TouchableOpacity>
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
}

function Stat({ label, value, colors }) {
  return (
    <View style={styles.stat}>
      <Text style={[styles.statValue, { color: colors.text }]}>{value}</Text>
      <Text style={[styles.statLabel, { color: colors.textSecondary }]}>{label}</Text>
    </View>
  );
}

function EmptyState({ icon, text, colors, onAction, actionText }) {
  return (
    <View style={styles.empty}>
      <Ionicons name={icon} size={52} color={colors.border} />
      <Text style={[styles.emptyText, { color: colors.textSecondary }]}>{text}</Text>
      {onAction && (
        <TouchableOpacity style={[styles.emptyBtn, { borderColor: colors.primary }]} onPress={onAction}>
          <Text style={[styles.emptyBtnText, { color: colors.primary }]}>{actionText}</Text>
        </TouchableOpacity>
      )}
    </View>
  );
}

function SettingsRow({ icon, label, onPress, colors, danger }) {
  return (
    <TouchableOpacity style={[styles.settingsRow, { borderBottomColor: colors.borderLight }]} onPress={onPress}>
      <View style={[styles.settingsIcon, { backgroundColor: danger ? '#FFEBEE' : colors.primaryLight }]}>
        <Ionicons name={icon} size={18} color={danger ? colors.error : colors.primary} />
      </View>
      <Text style={[styles.settingsLabel, { color: danger ? colors.error : colors.text }]}>{label}</Text>
      <Ionicons name="chevron-forward" size={16} color={colors.textTertiary} />
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  container:        { flex: 1 },
  profileCardOuter: { paddingHorizontal: SIZES.screenPadding, paddingTop: 16, paddingBottom: 4 },
  profileCard:      {
    borderRadius: 18,
    borderWidth: 2,
    paddingHorizontal: SIZES.screenPadding,
    paddingTop: 20,
    paddingBottom: 0,
  },
  profileCardInner: { flexDirection: 'row', alignItems: 'center', gap: 14, paddingBottom: 16 },
  avatarWrap:       { position: 'relative' },
  avatar:           { width: 64, height: 64, borderRadius: 32, borderWidth: 2 },
  avatarCameraBtn:  {
    position: 'absolute', bottom: 0, right: 0,
    width: 22, height: 22, borderRadius: 11,
    alignItems: 'center', justifyContent: 'center',
    borderWidth: 1.5, borderColor: '#fff',
  },
  avatarFallback:   { alignItems: 'center', justifyContent: 'center' },
  avatarLetter:     { fontSize: 26, fontWeight: '800' },
  profileInfo:      { flex: 1 },
  profileName:      { fontSize: 17, fontWeight: '800' },
  profileEmail:     { fontSize: 12, marginTop: 2 },
  profilePhone:     { fontSize: 12 },
  editBtn:          { width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center' },
  statsRow:         { flexDirection: 'row', borderTopWidth: 1, marginTop: 4 },
  stat:             { flex: 1, alignItems: 'center', paddingVertical: 14 },
  statValue:        { fontSize: 20, fontWeight: '800' },
  statLabel:        { fontSize: 11, marginTop: 2 },
  tabGrid:        { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  tabGridItem:    {
    width: '30.5%', borderRadius: 14, borderWidth: 1.5,
    paddingVertical: 12, alignItems: 'center', gap: 6,
  },
  tabGridLabel:   { fontSize: 11, fontWeight: '600', textAlign: 'center' },
  tabContent:     { padding: SIZES.screenPadding, gap: 12 },
  listCard:       { borderRadius: SIZES.cardRadius, padding: 14, gap: 6 },
  listCardTitle:  { fontSize: 14, fontWeight: '700', flex: 1, marginRight: 8 },
  listCardSub:    { fontSize: 13, lineHeight: 18 },
  listCardPrice:  { fontSize: 16, fontWeight: '800' },
  reviewTitle:    { fontSize: 13, fontWeight: '600' },
  adminReply:     { borderRadius: 8, padding: 10, gap: 3 },
  adminReplyLabel:{ fontSize: 10, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.5 },
  qBubble:        { borderRadius: 8, padding: 10, gap: 4 },
  qLabel:         { fontSize: 10, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.5 },
  pendingText:    { fontSize: 12 },
  newSupportBtn:  { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderRadius: SIZES.buttonRadius, paddingVertical: 14, marginBottom: 16 },
  newSupportBtnText: { fontSize: 15, fontWeight: '700', color: '#fff' },
  supStatusBadge: { borderRadius: 6, paddingHorizontal: 8, paddingVertical: 3 },
  supStatusText:  { fontSize: 11, fontWeight: '700' },
  navItem:        { flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: SIZES.cardRadius, padding: 16 },
  navItemText:    { flex: 1, fontSize: 15, fontWeight: '500' },
  settingsList:   { borderRadius: SIZES.cardRadius, overflow: 'hidden' },
  settingsRow:    { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderBottomWidth: 1 },
  settingsIcon:   { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center' },
  settingsLabel:  { flex: 1, fontSize: 15, fontWeight: '500' },
  seeAllBtn:      { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, borderWidth: 1, borderRadius: SIZES.buttonRadius, paddingVertical: 12 },
  seeAllText:     { fontSize: 14, fontWeight: '600' },
  empty:          { alignItems: 'center', paddingVertical: 40, gap: 12 },
  emptyText:      { fontSize: 15, fontWeight: '500' },
  emptyBtn:       { borderWidth: 1.5, borderRadius: SIZES.buttonRadius, paddingHorizontal: 22, paddingVertical: 10 },
  emptyBtnText:   { fontSize: 14, fontWeight: '600' },
  guestCard:      { margin: SIZES.screenPadding, borderRadius: SIZES.cardRadius, padding: 24, alignItems: 'center', gap: 14 },
  guestAvatar:    { width: 90, height: 90, borderRadius: 45, alignItems: 'center', justifyContent: 'center' },
  guestTitle:     { fontSize: 20, fontWeight: '800' },
  guestSub:       { fontSize: 14, textAlign: 'center', lineHeight: 20 },
  loginBtn:       { borderRadius: SIZES.buttonRadius, paddingHorizontal: 40, paddingVertical: 14, width: '100%', alignItems: 'center' },
  loginBtnText:   { fontSize: 15, fontWeight: '700', color: '#fff' },
  regBtn:         { borderWidth: 1.5, borderRadius: SIZES.buttonRadius, paddingHorizontal: 40, paddingVertical: 13, width: '100%', alignItems: 'center' },
  regBtnText:     { fontSize: 15, fontWeight: '600' },
  themeCard:      { flexDirection:'row', alignItems:'center', gap:12, margin:SIZES.screenPadding, marginTop:0, borderRadius:SIZES.cardRadius, padding:16 },
  themeLabel:     { flex:1, fontSize:15, fontWeight:'500' },
  modal:          { flex: 1 },
  modalContainer: { flex: 1 },
  modalHeader:    { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: SIZES.screenPadding, borderBottomWidth: 1 },
  modalTitle:     { fontSize: 18, fontWeight: '700' },
  modalBody:      { padding: SIZES.screenPadding, gap: 4 },
  fieldWrap:      { gap: 6 },
  fieldLabel:     { fontSize: 13, fontWeight: '600', marginTop: 12, marginBottom: 4 },
  input:          { borderWidth: 1, borderRadius: SIZES.inputRadius, paddingHorizontal: 14, paddingVertical: 12, fontSize: 15 },
  textarea:       { minHeight: 120, textAlignVertical: 'top' },
  saveBtn:        { borderRadius: SIZES.buttonRadius, paddingVertical: 15, alignItems: 'center', marginTop: 16 },
  saveBtnText:    { fontSize: 16, fontWeight: '700', color: '#fff' },
});
