import React, { useState, useRef, useEffect } from 'react';
import {
  View, Text, TouchableOpacity, StyleSheet, Modal,
  TextInput, Alert, ActivityIndicator, Image, ScrollView,
} from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useCart } from '../context/CartContext';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import { api, getImageUrl } from '../api';
import { SIZES } from '../constants/theme';

export default function CartScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { colors, shadows, isDark } = useTheme();
  const { items, removeItem, updateQty, clearCart, total, itemCount } = useCart();
  const { user } = useAuth();

  const [address,    setAddress]    = useState(user?.address || '');
  const [comment,    setComment]    = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [delivery,   setDelivery]   = useState('pickup');   // pickup | courier | post
  const [payment,    setPayment]    = useState('online');   // online | at_pickup | cash
  const [onlineTab,  setOnlineTab]  = useState('sbp');      // sbp | card

  // SBP modal
  const [sbpModal,   setSbpModal]   = useState(false);
  const [sbpOrderId, setSbpOrderId] = useState(null);
  const [sbpTotal,   setSbpTotal]   = useState(0);
  const [timeLeft,   setTimeLeft]   = useState(600);
  const timerRef = useRef(null);

  // Card modal
  const [cardModal,  setCardModal]  = useState(false);
  const [cardNumber, setCardNumber] = useState('');
  const [cardExpiry, setCardExpiry] = useState('');
  const [cardCvv,    setCardCvv]    = useState('');
  const [cardName,   setCardName]   = useState('');
  const [cardError,  setCardError]  = useState('');

  const startSbpTimer = () => {
    setTimeLeft(600);
    clearInterval(timerRef.current);
    timerRef.current = setInterval(() => {
      setTimeLeft(prev => {
        if (prev <= 1) { clearInterval(timerRef.current); return 0; }
        return prev - 1;
      });
    }, 1000);
  };

  const closeSbpModal = () => {
    clearInterval(timerRef.current);
    setSbpModal(false);
    navigation.navigate('ProfileTab', { screen: 'ProfileMain' });
  };

  useEffect(() => () => clearInterval(timerRef.current), []);

  // Способы оплаты по типу доставки (как на сайте)
  const PAY_OPTIONS = {
    pickup:  [
      { value: 'online',    label: 'Онлайн',            sub: 'СБП или банковская карта', icon: 'globe-outline' },
      { value: 'at_pickup', label: 'На складе',          sub: 'Наличными или картой при получении', icon: 'storefront-outline' },
    ],
    courier: [
      { value: 'online',    label: 'Онлайн',            sub: 'СБП или банковская карта', icon: 'globe-outline' },
      { value: 'cash',      label: 'Наличными курьеру', sub: 'При получении заказа', icon: 'cash-outline' },
    ],
    post:    [
      { value: 'online',    label: 'Онлайн',            sub: 'СБП или карта · единственный вариант', icon: 'globe-outline' },
    ],
  };

  const DELIVERY_OPTIONS = [
    { value: 'pickup',  label: 'Самовывоз',        icon: 'storefront-outline' },
    { value: 'courier', label: 'Курьер',            icon: 'bicycle-outline' },
    { value: 'post',    label: 'Почта России',      icon: 'mail-outline' },
  ];

  const onDeliveryChange = (val) => {
    setDelivery(val);
    // сбрасываем оплату если недоступна для нового типа доставки
    const opts = PAY_OPTIONS[val];
    if (!opts.find(o => o.value === payment)) setPayment(opts[0].value);
  };

  // Luhn check for card number validation
  const luhn = (num) => {
    const digits = num.replace(/\s/g, '');
    let sum = 0;
    let alt = false;
    for (let i = digits.length - 1; i >= 0; i--) {
      let n = parseInt(digits[i]);
      if (alt) { n *= 2; if (n > 9) n -= 9; }
      sum += n;
      alt = !alt;
    }
    return sum % 10 === 0;
  };

  const submitCardPayment = async () => {
    setCardError('');
    const raw = cardNumber.replace(/\s/g, '');
    if (raw.length < 16) { setCardError('Введите полный номер карты (16 цифр)'); return; }
    if (!luhn(raw))       { setCardError('Неверный номер карты'); return; }
    const [mm, yy] = cardExpiry.split('/');
    const now = new Date();
    if (!mm || !yy || parseInt(mm) < 1 || parseInt(mm) > 12) { setCardError('Неверный срок действия'); return; }
    if (parseInt('20' + yy) < now.getFullYear() || (parseInt('20' + yy) === now.getFullYear() && parseInt(mm) < now.getMonth() + 1)) {
      setCardError('Срок действия карты истёк'); return;
    }
    if (cardCvv.length < 3) { setCardError('Введите CVV (3 цифры)'); return; }
    if (!cardName.trim())   { setCardError('Введите имя держателя карты'); return; }

    setCardModal(false);
    await doPlaceOrder();
  };

  const doPlaceOrder = async () => {
    const paymentLabel = payment === 'online'
      ? (onlineTab === 'sbp' ? 'СБП — быстрые платежи' : 'Банковская карта онлайн')
      : payment === 'at_pickup' ? 'Оплата на складе'
      : 'Наличными курьеру';

    setSubmitting(true);
    const res = await api.placeOrder({
      items: items.map(i => ({
        product_id: i.product.id,
        name:       i.product.name,
        article:    i.product.article || '',
        price:      i.product.price,
        quantity:   i.quantity,
      })),
      address:       delivery === 'pickup' ? 'Самовывоз' : address.trim(),
      comment:       comment.trim(),
      delivery_type: delivery,
      payment_type:  payment === 'online' ? `online_${onlineTab}` : payment,
      payment_label: paymentLabel,
    });
    setSubmitting(false);

    if (res.success) {
      clearCart();
      if (payment === 'online' && onlineTab === 'sbp') {
        setSbpOrderId(res.order_id);
        setSbpTotal(total);
        setSbpModal(true);
        startSbpTimer();
      } else {
        Alert.alert('Заказ оформлен!', `Ваш заказ №${res.order_id} принят.\nМы свяжемся с вами для подтверждения.`, [
          { text: 'Мои заказы', onPress: () => navigation.navigate('ProfileTab', { screen: 'Orders' }) },
          { text: 'ОК' },
        ]);
      }
    } else {
      Alert.alert('Ошибка', res.message);
    }
  };

  const placeOrder = async () => {
    if (delivery !== 'pickup' && !address.trim()) {
      Alert.alert('Ошибка', 'Укажите адрес доставки'); return;
    }
    if (!user) { navigation.navigate('ProfileTab', { screen: 'Login' }); return; }

    // Card payment — show card form modal first
    if (payment === 'online' && onlineTab === 'card') {
      setCardNumber(''); setCardExpiry(''); setCardCvv(''); setCardName(''); setCardError('');
      setCardModal(true);
      return;
    }

    await doPlaceOrder();
  };

  return (
    <View style={[styles.container, { backgroundColor: colors.surface, paddingTop: insets.top }]}>

      {/* Header */}
      <View style={[styles.headerOuter, { backgroundColor: colors.surface }]}>
        <View style={[styles.headerCard, { backgroundColor: isDark ? '#000' : '#fff', borderColor: colors.primary }]}>
          <Text style={[styles.title, { color: colors.text }]}>
            Корзина{itemCount > 0 ? ` (${itemCount})` : ''}
          </Text>
          {items.length > 0 && (
            <TouchableOpacity onPress={() => Alert.alert('Очистить корзину?', '', [
              { text: 'Отмена', style: 'cancel' },
              { text: 'Очистить', style: 'destructive', onPress: clearCart },
            ])}>
              <Text style={[styles.clearText, { color: colors.error }]}>Очистить</Text>
            </TouchableOpacity>
          )}
        </View>
      </View>

      {/* Empty state */}
      {items.length === 0 && !sbpModal && (
        <View style={[styles.emptyWrap, styles.center]}>
          <Ionicons name="cart-outline" size={72} color={colors.border} />
          <Text style={[styles.emptyTitle, { color: colors.text }]}>Корзина пуста</Text>
          <Text style={[styles.emptySub, { color: colors.textSecondary }]}>Добавьте товары из каталога</Text>
          <TouchableOpacity
            style={[styles.shopBtn, { backgroundColor: colors.primary }]}
            onPress={() => navigation.navigate('CatalogTab')}
          >
            <Text style={styles.shopBtnText}>Перейти в каталог</Text>
          </TouchableOpacity>
        </View>
      )}

      {/* Cart content */}
      {items.length > 0 && (
      <ScrollView contentContainerStyle={{ paddingBottom: 120 }}>
        {/* Items */}
        <View style={styles.itemsList}>
          {items.map(({ product, quantity }) => (
            <View key={product.id} style={[styles.itemCard, { backgroundColor: colors.card, ...shadows.sm }]}>
              {product.image && (
                <Image source={{ uri: getImageUrl(product.image) }} style={styles.itemImg} resizeMode="cover" />
              )}
              <View style={styles.itemInfo}>
                <Text style={[styles.itemName, { color: colors.text }]} numberOfLines={2}>{product.name}</Text>
                <Text style={[styles.itemArticle, { color: colors.textTertiary }]}>Арт. {product.article}</Text>
                <Text style={[styles.itemPrice, { color: colors.primary }]}>
                  {Number(product.price).toLocaleString('ru-RU')} ₽
                </Text>
              </View>
              <View style={styles.itemRight}>
                <TouchableOpacity onPress={() => removeItem(product.id)} style={styles.removeBtn}>
                  <Ionicons name="trash-outline" size={18} color={colors.textSecondary} />
                </TouchableOpacity>
                <View style={[styles.qtyRow, { backgroundColor: colors.surface, borderColor: colors.border }]}>
                  <TouchableOpacity onPress={() => updateQty(product.id, quantity - 1)} style={styles.qtyBtn}>
                    <Ionicons name="remove" size={16} color={colors.text} />
                  </TouchableOpacity>
                  <Text style={[styles.qtyNum, { color: colors.text }]}>{quantity}</Text>
                  <TouchableOpacity
                    onPress={() => updateQty(product.id, quantity + 1)}
                    style={[styles.qtyBtn, quantity >= (product.available ?? 99) && styles.qtyBtnDisabled]}
                    disabled={quantity >= (product.available ?? 99)}
                  >
                    <Ionicons name="add" size={16} color={quantity >= (product.available ?? 99) ? colors.textSecondary : colors.text} />
                  </TouchableOpacity>
                </View>
                <Text style={[styles.itemTotal, { color: colors.text }]}>
                  {Number(product.price * quantity).toLocaleString('ru-RU')} ₽
                </Text>
              </View>
            </View>
          ))}
        </View>

        {/* Доставка */}
        <View style={[styles.section, { backgroundColor: colors.card, ...shadows.sm }]}>
          <Text style={[styles.sectionTitle, { color: colors.text }]}>Доставка</Text>
          <View style={styles.optionsRow}>
            {DELIVERY_OPTIONS.map(o => {
              const active = delivery === o.value;
              return (
                <TouchableOpacity
                  key={o.value}
                  style={[styles.optionBtn, {
                    borderColor:       active ? colors.primary : colors.border,
                    backgroundColor:   active ? colors.primaryLight : colors.inputBg,
                  }]}
                  onPress={() => onDeliveryChange(o.value)}
                >
                  <Ionicons name={o.icon} size={20} color={active ? colors.primary : colors.textSecondary} />
                  <Text style={[styles.optionLabel, { color: active ? colors.primary : colors.text }]}>{o.label}</Text>
                </TouchableOpacity>
              );
            })}
          </View>

          {delivery !== 'pickup' && (
            <View style={styles.fieldWrap}>
              <Text style={[styles.label, { color: colors.textSecondary }]}>
                Адрес {delivery === 'post' ? 'для отправки' : 'доставки'}{' '}
                <Text style={{ color: colors.error }}>*</Text>
              </Text>
              <TextInput
                style={[styles.input, { backgroundColor: colors.inputBg, borderColor: colors.border, color: colors.text }]}
                value={address}
                onChangeText={setAddress}
                placeholder={delivery === 'post' ? 'Индекс, город, улица, дом' : 'г. Москва, ул. Примерная, д. 1'}
                placeholderTextColor={colors.textTertiary}
                multiline
              />
            </View>
          )}

          <View style={styles.fieldWrap}>
            <Text style={[styles.label, { color: colors.textSecondary }]}>Комментарий</Text>
            <TextInput
              style={[styles.input, { backgroundColor: colors.inputBg, borderColor: colors.border, color: colors.text }]}
              value={comment}
              onChangeText={setComment}
              placeholder="Подъезд, домофон, пожелания..."
              placeholderTextColor={colors.textTertiary}
              multiline
            />
          </View>
        </View>

        {/* Оплата */}
        <View style={[styles.section, { backgroundColor: colors.card, ...shadows.sm }]}>
          <Text style={[styles.sectionTitle, { color: colors.text }]}>Оплата</Text>
          {PAY_OPTIONS[delivery].map(o => {
            const active = payment === o.value;
            return (
              <TouchableOpacity
                key={o.value}
                style={[styles.payOption, {
                  borderColor:     active ? colors.primary : colors.border,
                  backgroundColor: active ? colors.primaryLight : colors.inputBg,
                }]}
                onPress={() => setPayment(o.value)}
              >
                <View style={[styles.payRadio, { borderColor: active ? colors.primary : colors.border }]}>
                  {active && <View style={[styles.payRadioInner, { backgroundColor: colors.primary }]} />}
                </View>
                <Ionicons name={o.icon} size={20} color={active ? colors.primary : colors.textSecondary} />
                <View style={{ flex: 1 }}>
                  <Text style={[styles.payLabel, { color: active ? colors.primary : colors.text }]}>{o.label}</Text>
                  <Text style={[styles.paySub, { color: colors.textSecondary }]}>{o.sub}</Text>
                </View>
              </TouchableOpacity>
            );
          })}

          {/* СБП / Карта переключатель */}
          {payment === 'online' && (
            <View style={[styles.onlineTabs, { backgroundColor: colors.surface, borderColor: colors.border }]}>
              {[
                { v: 'sbp',  label: 'СБП' },
                { v: 'card', label: 'Карта' },
              ].map(t => (
                <TouchableOpacity
                  key={t.v}
                  style={[styles.onlineTab, onlineTab === t.v && { backgroundColor: colors.primary }]}
                  onPress={() => setOnlineTab(t.v)}
                >
                  <Text style={[styles.onlineTabTxt, { color: onlineTab === t.v ? '#fff' : colors.text }]}>
                    {t.label}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          )}
        </View>

        {/* Summary */}
        <View style={[styles.summary, { backgroundColor: colors.card, ...shadows.sm }]}>
          <View style={styles.summaryRow}>
            <Text style={[styles.summaryLabel, { color: colors.textSecondary }]}>Товаров:</Text>
            <Text style={[styles.summaryValue, { color: colors.text }]}>{itemCount} шт.</Text>
          </View>
          <View style={styles.summaryRow}>
            <Text style={[styles.summaryLabel, { color: colors.textSecondary }]}>Доставка:</Text>
            <Text style={[styles.summaryValue, { color: colors.success }]}>Бесплатно</Text>
          </View>
          <View style={[styles.summaryTotal, { borderTopColor: colors.border }]}>
            <Text style={[styles.totalLabel, { color: colors.text }]}>Итого:</Text>
            <Text style={[styles.totalValue, { color: colors.primary }]}>
              {Number(total).toLocaleString('ru-RU')} ₽
            </Text>
          </View>
        </View>
      </ScrollView>
      )}

      {/* Bottom CTA — только когда корзина не пуста */}
      {items.length > 0 && (
        <View style={[styles.bottomBar, { backgroundColor: colors.card, borderTopColor: colors.border, ...shadows.lg, paddingBottom: insets.bottom + 12 }]}>
          <TouchableOpacity
            style={[styles.orderBtn, { backgroundColor: colors.primary }, submitting && { opacity: 0.7 }]}
            onPress={placeOrder}
            disabled={submitting}
          >
            {submitting
              ? <ActivityIndicator color="#fff" />
              : <>
                  <Ionicons name="checkmark-circle-outline" size={20} color="#fff" />
                  <Text style={styles.orderBtnText}>Оформить заказ · {Number(total).toLocaleString('ru-RU')} ₽</Text>
                </>
            }
          </TouchableOpacity>
        </View>
      )}

      {/* SBP QR Modal — всегда в дереве, виден только когда sbpModal=true */}
      <Modal visible={sbpModal} animationType="slide" presentationStyle="pageSheet" onRequestClose={closeSbpModal}>
        <View style={[styles.sbpModal, { backgroundColor: colors.card }]}>
          {/* Header */}
          <View style={[styles.sbpHeader, { borderBottomColor: colors.border }]}>
            <View style={styles.sbpHeaderLeft}>
              <View style={[styles.sbpBadge, { backgroundColor: '#1A8EFF' }]}>
                <Text style={styles.sbpBadgeTxt}>СБП</Text>
              </View>
              <Text style={[styles.sbpTitle, { color: colors.text }]}>Оплата через СБП</Text>
            </View>
            <TouchableOpacity onPress={closeSbpModal} style={[styles.sbpClose, { backgroundColor: colors.inputBg }]}>
              <Ionicons name="close" size={20} color={colors.text} />
            </TouchableOpacity>
          </View>

          {/* Amount */}
          <View style={styles.sbpAmountRow}>
            <Text style={[styles.sbpAmountLabel, { color: colors.textSecondary }]}>К оплате:</Text>
            <Text style={[styles.sbpAmount, { color: colors.primary }]}>
              {Number(sbpTotal).toLocaleString('ru-RU')} ₽
            </Text>
          </View>

          {/* QR Code */}
          <View style={[styles.sbpQrWrap, { borderColor: colors.border, backgroundColor: '#fff' }]}>
            {sbpOrderId ? (
              <QRCode
                value={`ST00012|Name=DrivewayMarket|BankName=Demo|Sum=${Math.round(sbpTotal * 100)}|Purpose=Заказ №${sbpOrderId}`}
                size={220}
                color="#000"
                backgroundColor="#fff"
              />
            ) : (
              <ActivityIndicator size="large" color={colors.primary} style={{ width: 220, height: 220 }} />
            )}
          </View>

          {/* Timer */}
          <View style={[styles.sbpTimerRow, { backgroundColor: colors.inputBg, borderRadius: 12 }]}>
            <Ionicons name="time-outline" size={18} color={timeLeft > 60 ? colors.textSecondary : colors.error} />
            <Text style={[styles.sbpTimerTxt, { color: timeLeft > 60 ? colors.text : colors.error }]}>
              {`${String(Math.floor(timeLeft / 60)).padStart(2, '0')}:${String(timeLeft % 60).padStart(2, '0')}`}
            </Text>
            <Text style={[styles.sbpTimerLabel, { color: colors.textSecondary }]}>— время действия QR</Text>
          </View>

          {/* Instructions */}
          <Text style={[styles.sbpInstr, { color: colors.textSecondary }]}>
            Откройте банковское приложение и отсканируйте QR-код, или нажмите «Оплатить» ниже.
          </Text>

          {/* Action buttons */}
          <TouchableOpacity
            style={[styles.sbpPaidBtn, { backgroundColor: colors.primary }]}
            onPress={closeSbpModal}
          >
            <Ionicons name="checkmark-circle-outline" size={20} color="#fff" />
            <Text style={styles.sbpPaidBtnTxt}>Я оплатил(а)</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.sbpCancelBtn, { borderColor: colors.border }]}
            onPress={closeSbpModal}
          >
            <Text style={[styles.sbpCancelBtnTxt, { color: colors.textSecondary }]}>Отменить</Text>
          </TouchableOpacity>
        </View>
      </Modal>

      {/* Card Payment Modal */}
      <Modal visible={cardModal} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setCardModal(false)}>
        <ScrollView style={[styles.sbpModal, { backgroundColor: colors.card }]} keyboardShouldPersistTaps="handled">
          {/* Header */}
          <View style={[styles.sbpHeader, { borderBottomColor: colors.border }]}>
            <View style={styles.sbpHeaderLeft}>
              <View style={[styles.sbpBadge, { backgroundColor: '#2c3e50' }]}>
                <Ionicons name="card-outline" size={14} color="#fff" />
              </View>
              <Text style={[styles.sbpTitle, { color: colors.text }]}>Оплата картой</Text>
            </View>
            <TouchableOpacity onPress={() => setCardModal(false)} style={[styles.sbpClose, { backgroundColor: colors.inputBg }]}>
              <Ionicons name="close" size={20} color={colors.text} />
            </TouchableOpacity>
          </View>

          {/* Amount */}
          <View style={styles.sbpAmountRow}>
            <Text style={[styles.sbpAmountLabel, { color: colors.textSecondary }]}>К оплате:</Text>
            <Text style={[styles.sbpAmount, { color: colors.primary }]}>{Number(total).toLocaleString('ru-RU')} ₽</Text>
          </View>

          {/* Security disclaimer */}
          <View style={[styles.cardDisclaimer, { backgroundColor: colors.inputBg, borderColor: colors.border }]}>
            <Ionicons name="shield-checkmark-outline" size={18} color={colors.success} />
            <Text style={[styles.cardDisclaimerTxt, { color: colors.textSecondary }]}>
              Данные карты не сохраняются и используются только для оформления этого заказа.
            </Text>
          </View>

          {/* Card number */}
          <View style={styles.cardFieldWrap}>
            <Text style={[styles.cardLabel, { color: colors.textSecondary }]}>Номер карты</Text>
            <TextInput
              style={[styles.cardInput, { backgroundColor: colors.inputBg, borderColor: colors.border, color: colors.text }]}
              placeholder="0000 0000 0000 0000"
              placeholderTextColor={colors.textTertiary}
              keyboardType="numeric"
              maxLength={19}
              value={cardNumber}
              onChangeText={v => {
                const digits = v.replace(/\D/g, '').slice(0, 16);
                setCardNumber(digits.replace(/(.{4})/g, '$1 ').trim());
              }}
            />
          </View>

          <View style={styles.cardRow}>
            <View style={[styles.cardFieldWrap, { flex: 1, marginRight: 10 }]}>
              <Text style={[styles.cardLabel, { color: colors.textSecondary }]}>Срок действия</Text>
              <TextInput
                style={[styles.cardInput, { backgroundColor: colors.inputBg, borderColor: colors.border, color: colors.text }]}
                placeholder="MM/YY"
                placeholderTextColor={colors.textTertiary}
                keyboardType="numeric"
                maxLength={5}
                value={cardExpiry}
                onChangeText={v => {
                  const digits = v.replace(/\D/g, '').slice(0, 4);
                  setCardExpiry(digits.length > 2 ? digits.slice(0, 2) + '/' + digits.slice(2) : digits);
                }}
              />
            </View>
            <View style={[styles.cardFieldWrap, { flex: 1 }]}>
              <Text style={[styles.cardLabel, { color: colors.textSecondary }]}>CVV</Text>
              <TextInput
                style={[styles.cardInput, { backgroundColor: colors.inputBg, borderColor: colors.border, color: colors.text }]}
                placeholder="•••"
                placeholderTextColor={colors.textTertiary}
                keyboardType="numeric"
                maxLength={4}
                secureTextEntry
                value={cardCvv}
                onChangeText={v => setCardCvv(v.replace(/\D/g, '').slice(0, 4))}
              />
            </View>
          </View>

          <View style={styles.cardFieldWrap}>
            <Text style={[styles.cardLabel, { color: colors.textSecondary }]}>Имя держателя</Text>
            <TextInput
              style={[styles.cardInput, { backgroundColor: colors.inputBg, borderColor: colors.border, color: colors.text }]}
              placeholder="IVAN IVANOV"
              placeholderTextColor={colors.textTertiary}
              autoCapitalize="characters"
              value={cardName}
              onChangeText={setCardName}
            />
          </View>

          {cardError ? (
            <Text style={[styles.cardErrorTxt, { color: colors.error }]}>{cardError}</Text>
          ) : null}

          <TouchableOpacity
            style={[styles.sbpPaidBtn, { backgroundColor: colors.primary, marginTop: 8 }]}
            onPress={submitCardPayment}
          >
            <Ionicons name="lock-closed-outline" size={18} color="#fff" />
            <Text style={styles.sbpPaidBtnTxt}>Оплатить {Number(total).toLocaleString('ru-RU')} ₽</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.sbpCancelBtn, { borderColor: colors.border, marginBottom: 32 }]}
            onPress={() => setCardModal(false)}
          >
            <Text style={[styles.sbpCancelBtnTxt, { color: colors.textSecondary }]}>Отмена</Text>
          </TouchableOpacity>
        </ScrollView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container:    { flex: 1 },
  center:       { flex: 1, alignItems: 'center', justifyContent: 'center' },
  headerOuter:  { paddingHorizontal: SIZES.screenPadding, paddingTop: 10, paddingBottom: 8 },
  headerCard:   {
    borderRadius: 18, borderWidth: 2,
    paddingHorizontal: SIZES.screenPadding, paddingVertical: 14,
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
  },
  title:        { fontSize: 22, fontWeight: '800' },
  clearText:    { fontSize: 14, fontWeight: '600' },
  emptyWrap:    { gap: 14, padding: SIZES.screenPadding },
  itemsList:   { padding: SIZES.screenPadding, gap: 12 },
  itemCard:    { flexDirection: 'row', gap: 12, borderRadius: SIZES.cardRadius, padding: 12 },
  itemImg:     { width: 80, height: 80, borderRadius: 10 },
  itemInfo:    { flex: 1, gap: 4 },
  itemName:    { fontSize: 13, fontWeight: '500', lineHeight: 18 },
  itemArticle: { fontSize: 11 },
  itemPrice:   { fontSize: 15, fontWeight: '700' },
  itemRight:   { alignItems: 'center', gap: 8 },
  removeBtn:   { padding: 4 },
  qtyRow:      { flexDirection: 'row', alignItems: 'center', borderRadius: 8, borderWidth: 1, overflow: 'hidden' },
  qtyBtn:          { padding: 6, width: 32, alignItems: 'center' },
  qtyBtnDisabled:  { opacity: 0.3 },
  qtyNum:      { fontSize: 14, fontWeight: '700', minWidth: 24, textAlign: 'center' },
  itemTotal:   { fontSize: 13, fontWeight: '700' },
  section:      { margin: SIZES.screenPadding, marginTop: 0, borderRadius: SIZES.cardRadius, padding: 16, gap: 12 },
  sectionTitle: { fontSize: 16, fontWeight: '700' },
  fieldWrap:    { gap: 5 },
  label:        { fontSize: 13, fontWeight: '500' },
  input:        { borderWidth: 1, borderRadius: SIZES.inputRadius, paddingHorizontal: 12, paddingVertical: 10, fontSize: 14, minHeight: 42 },
  /* Delivery options */
  optionsRow:   { flexDirection: 'row', gap: 8 },
  optionBtn:    { flex: 1, borderWidth: 1.5, borderRadius: 12, paddingVertical: 10, alignItems: 'center', gap: 4 },
  optionLabel:  { fontSize: 11, fontWeight: '600', textAlign: 'center' },
  /* Payment */
  payOption:    { flexDirection: 'row', alignItems: 'center', gap: 10, borderWidth: 1.5, borderRadius: 12, padding: 12 },
  payRadio:     { width: 20, height: 20, borderRadius: 10, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
  payRadioInner:{ width: 10, height: 10, borderRadius: 5 },
  payLabel:     { fontSize: 14, fontWeight: '600' },
  paySub:       { fontSize: 11, marginTop: 2 },
  /* Online sub-tabs */
  onlineTabs:   { flexDirection: 'row', borderWidth: 1, borderRadius: 10, overflow: 'hidden' },
  onlineTab:    { flex: 1, paddingVertical: 10, alignItems: 'center' },
  onlineTabTxt: { fontSize: 13, fontWeight: '700' },
  summary:     { marginHorizontal: SIZES.screenPadding, marginBottom: 16, borderRadius: SIZES.cardRadius, padding: 16, gap: 8 },
  summaryRow:  { flexDirection: 'row', justifyContent: 'space-between' },
  summaryLabel:{ fontSize: 14 },
  summaryValue:{ fontSize: 14, fontWeight: '600' },
  summaryTotal:{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingTop: 12, marginTop: 4, borderTopWidth: 1 },
  totalLabel:  { fontSize: 16, fontWeight: '700' },
  totalValue:  { fontSize: 22, fontWeight: '800' },
  bottomBar:   { position: 'absolute', bottom: 0, left: 0, right: 0, padding: SIZES.screenPadding, paddingTop: 12, borderTopWidth: 1 },
  orderBtn:    { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, borderRadius: SIZES.buttonRadius, paddingVertical: 15 },
  orderBtnText:{ fontSize: 16, fontWeight: '700', color: '#fff' },
  emptyTitle:  { fontSize: 20, fontWeight: '700' },
  emptySub:    { fontSize: 14 },
  shopBtn:     { borderRadius: SIZES.buttonRadius, paddingHorizontal: 32, paddingVertical: 14 },
  shopBtnText: { fontSize: 15, fontWeight: '700', color: '#fff' },

  /* SBP Modal */
  sbpModal:       { flex: 1, paddingHorizontal: 24, paddingBottom: 32 },
  sbpHeader:      { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 18, borderBottomWidth: 1 },
  sbpHeaderLeft:  { flexDirection: 'row', alignItems: 'center', gap: 10 },
  sbpBadge:       { borderRadius: 8, paddingHorizontal: 8, paddingVertical: 4 },
  sbpBadgeTxt:    { color: '#fff', fontSize: 13, fontWeight: '800' },
  sbpTitle:       { fontSize: 17, fontWeight: '700' },
  sbpClose:       { width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center' },
  sbpAmountRow:   { flexDirection: 'row', alignItems: 'baseline', gap: 8, marginTop: 20, marginBottom: 16 },
  sbpAmountLabel: { fontSize: 16 },
  sbpAmount:      { fontSize: 28, fontWeight: '800' },
  sbpQrWrap:      { alignSelf: 'center', borderWidth: 1.5, borderRadius: 16, padding: 12, marginBottom: 20 },
  sbpQrImg:       { width: 220, height: 220 },
  sbpTimerRow:    { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 16, paddingVertical: 10, marginBottom: 16 },
  sbpTimerTxt:    { fontSize: 18, fontWeight: '800', fontVariant: ['tabular-nums'] },
  sbpTimerLabel:  { fontSize: 13 },
  sbpInstr:       { fontSize: 13, textAlign: 'center', lineHeight: 20, marginBottom: 24 },
  sbpPaidBtn:     { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderRadius: SIZES.buttonRadius, paddingVertical: 15, marginBottom: 12 },
  sbpPaidBtnTxt:  { fontSize: 16, fontWeight: '700', color: '#fff' },
  sbpCancelBtn:   { borderWidth: 1.5, borderRadius: SIZES.buttonRadius, paddingVertical: 13, alignItems: 'center' },
  sbpCancelBtnTxt:{ fontSize: 15, fontWeight: '600' },

  // Card modal
  cardDisclaimer:  { flexDirection: 'row', alignItems: 'flex-start', gap: 8, borderRadius: 10, borderWidth: 1, padding: 12, marginHorizontal: 20, marginBottom: 16 },
  cardDisclaimerTxt: { flex: 1, fontSize: 12, lineHeight: 17 },
  cardFieldWrap:   { marginHorizontal: 20, marginBottom: 14 },
  cardRow:         { flexDirection: 'row', marginHorizontal: 20, marginBottom: 14 },
  cardLabel:       { fontSize: 12, fontWeight: '600', marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.5 },
  cardInput:       { borderWidth: 1.5, borderRadius: 10, paddingHorizontal: 14, paddingVertical: 12, fontSize: 16, letterSpacing: 1 },
  cardErrorTxt:    { marginHorizontal: 20, marginBottom: 12, fontSize: 13, fontWeight: '500' },
});
