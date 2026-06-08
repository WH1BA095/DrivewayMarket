import React, { useEffect, useState, useCallback, useMemo } from 'react';
import {
  View, Text, Image, ScrollView, TouchableOpacity,
  StyleSheet, TextInput, ActivityIndicator, RefreshControl, Dimensions, Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api, getImageUrl } from '../api';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import { SIZES } from '../constants/theme';
import ProductCard from '../components/ProductCard';
import CategoryCard from '../components/CategoryCard';
import Logo from '../components/Logo';

const { width: SW } = Dimensions.get('window');
const CARD_GAP   = 12;
const PADDING    = SIZES.screenPadding;
const CARD_W     = (SW - PADDING * 2 - CARD_GAP) / 2;

export default function HomeScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const { colors, shadows, isDark } = useTheme();

  const [categories,  setCategories]  = useState([]);
  const [featured,    setFeatured]    = useState([]);
  const [favorites,   setFavorites]   = useState(new Set());
  const [searchQuery, setSearchQuery] = useState('');
  const [loading,     setLoading]     = useState(true);
  const [refreshing,  setRefreshing]  = useState(false);

  const s = useMemo(() => makeStyles(colors, shadows), [colors, shadows]);

  const load = useCallback(async () => {
    const [catRes, prodRes] = await Promise.all([
      api.categories(),
      api.products({ per_page: 6 }),
    ]);
    if (catRes.success)  setCategories(catRes.categories);
    if (prodRes.success) setFeatured(prodRes.products);
    if (user) {
      const favRes = await api.favorites();
      if (favRes.success) setFavorites(new Set(favRes.favorites.map(f => Number(f.id))));
    }
    setLoading(false);
    setRefreshing(false);
  }, [user]);

  useEffect(() => { load(); }, [load]);

  const onRefresh = () => { setRefreshing(true); load(); };

  const handleSearch = () => {
    if (searchQuery.trim()) {
      navigation.navigate('CatalogTab', {
        screen: 'CatalogMain',
        params: { search: searchQuery.trim() },
      });
    }
  };

  const toggleFav = async (productId) => {
    if (!user) { navigation.navigate('ProfileTab', { screen: 'Login' }); return; }
    const id = Number(productId);
    try {
      const res = await api.toggleFavorite(id);
      if (res.success) {
        setFavorites(prev => {
          const next = new Set(prev);
          res.action === 'added' ? next.add(id) : next.delete(id);
          return next;
        });
      } else {
        Alert.alert('Ошибка избранного', res.message || JSON.stringify(res));
      }
    } catch (e) {
      Alert.alert('Ошибка сети', e.message || String(e));
    }
  };

  if (loading) {
    return (
      <View style={[s.center, { backgroundColor: colors.surface }]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.surface }}
      contentContainerStyle={{ paddingBottom: 32 }}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />
      }
      showsVerticalScrollIndicator={false}
    >
      {/* ─── Header card (как на сайте: скруглённый прямоугольник с красной рамкой) ── */}
      <View style={[s.headerOuter, { paddingTop: insets.top + 8, backgroundColor: colors.surface }]}>
        <View style={[s.headerCard, { backgroundColor: isDark ? '#000' : '#fff' }]}>
          {/* Строка лого + аватар */}
          <View style={s.headerTop}>
            {/* Светлая тема → тёмный лого (onLight), тёмная → светлый лого (onDark) */}
            <Logo variant={isDark ? 'onDark' : 'onLight'} size="lg" />
            <TouchableOpacity
              onPress={() => navigation.navigate('ProfileTab')}
              style={s.avatarBtn}
            >
              {user && getImageUrl(user.avatar) ? (
                <Image
                  source={{ uri: getImageUrl(user.avatar) }}
                  style={[s.headerAvatar, { borderColor: colors.primary }]}
                />
              ) : user ? (
                <View style={[s.headerAvatar, s.headerAvatarFallback, { backgroundColor: colors.primaryLight, borderColor: colors.primary }]}>
                  <Text style={[s.headerAvatarLetter, { color: colors.primary }]}>
                    {(user.firstname || '?')[0].toUpperCase()}
                  </Text>
                </View>
              ) : (
                <Ionicons name="person-circle-outline" size={32} color={colors.primary} />
              )}
            </TouchableOpacity>
          </View>

          {/* Поиск внутри той же карточки */}
          <View style={s.searchRow}>
            <View style={[s.searchBox, { backgroundColor: isDark ? '#1a1a1a' : colors.inputBg, borderColor: isDark ? '#333' : colors.border }]}>
              <Ionicons name="search-outline" size={17} color={colors.textSecondary} />
              <TextInput
                style={[s.searchInput, { color: colors.text }]}
                placeholder="Поиск по названию или артикулу..."
                placeholderTextColor={colors.textTertiary}
                value={searchQuery}
                onChangeText={setSearchQuery}
                onSubmitEditing={handleSearch}
                returnKeyType="search"
              />
              {searchQuery.length > 0 && (
                <TouchableOpacity onPress={() => setSearchQuery('')}>
                  <Ionicons name="close-circle" size={17} color={colors.textTertiary} />
                </TouchableOpacity>
              )}
            </View>
            <TouchableOpacity
              style={[s.searchBtn, { backgroundColor: colors.primary }]}
              onPress={handleSearch}
            >
              <Ionicons name="arrow-forward" size={19} color="#fff" />
            </TouchableOpacity>
          </View>
        </View>
      </View>

      {/* ─── VIN Banner (вверху!) ────────────────────────────── */}
      <TouchableOpacity
        style={[s.vinBanner, { backgroundColor: colors.primary }]}
        activeOpacity={0.88}
      >
        <View style={s.vinContent}>
          <Ionicons name="construct-outline" size={32} color="rgba(255,255,255,0.9)" />
          <View style={{ flex: 1 }}>
            <Text style={s.vinTitle}>Подберём деталь по VIN</Text>
            <Text style={s.vinSub}>Позвоните или напишите в чат — найдём любую запчасть</Text>
          </View>
          <View style={s.vinArrow}>
            <Ionicons name="chevron-forward" size={18} color={colors.primary} />
          </View>
        </View>
      </TouchableOpacity>

      {/* ─── Categories 2×2 ─────────────────────────────────── */}
      <View style={s.section}>
        <View style={s.sectionHeader}>
          <Text style={[s.sectionTitle, { color: colors.text }]}>Категории</Text>
          <TouchableOpacity onPress={() => navigation.navigate('CatalogTab')}>
            <Text style={[s.seeAll, { color: colors.primary }]}>Все товары ›</Text>
          </TouchableOpacity>
        </View>

        {chunk(categories.slice(0, 4), 2).map((row, ri) => (
          <View key={ri} style={s.gridRow}>
            {row.map(cat => (
              <CategoryCard
                key={cat.id}
                category={cat}
                style={{ width: CARD_W }}
                onPress={() => navigation.navigate('CatalogTab', {
                  screen: 'CatalogMain',
                  params: { category_id: cat.id, categoryName: cat.name },
                })}
              />
            ))}
            {row.length === 1 && <View style={{ width: CARD_W }} />}
          </View>
        ))}
      </View>

      {/* ─── Popular Products 2×3 ───────────────────────────── */}
      <View style={s.section}>
        <View style={s.sectionHeader}>
          <Text style={[s.sectionTitle, { color: colors.text }]}>Популярные товары</Text>
          <TouchableOpacity onPress={() => navigation.navigate('CatalogTab')}>
            <Text style={[s.seeAll, { color: colors.primary }]}>Все ›</Text>
          </TouchableOpacity>
        </View>

        {chunk(featured, 2).map((row, ri) => (
          <View key={ri} style={s.gridRow}>
            {row.map(item => (
              <ProductCard
                key={item.id}
                product={item}
                style={{ width: CARD_W }}
                isFavorited={favorites.has(Number(item.id))}
                onFavoriteToggle={toggleFav}
                onPress={() => navigation.navigate('Product', { id: item.id })}
              />
            ))}
            {row.length === 1 && <View style={{ width: CARD_W }} />}
          </View>
        ))}
      </View>
    </ScrollView>
  );
}

function makeStyles(colors, shadows) {
  return StyleSheet.create({
    center:       { flex: 1, alignItems: 'center', justifyContent: 'center' },

    /* Header — скруглённая карточка с красной рамкой, как на сайте */
    headerOuter:  {
      paddingHorizontal: PADDING,
      paddingBottom: 8,
    },
    headerCard:   {
      borderRadius: 18,
      borderWidth: 2,
      borderColor: colors.primary,
      paddingHorizontal: PADDING,
      paddingVertical: 12,
      gap: 10,
    },
    headerTop:    {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
    },
    avatarBtn:          { padding: 4 },
    headerAvatar:       { width: 34, height: 34, borderRadius: 17, borderWidth: 2 },
    headerAvatarFallback:{ alignItems: 'center', justifyContent: 'center' },
    headerAvatarLetter: { fontSize: 15, fontWeight: '800' },

    /* Search (внутри headerCard) */
    searchRow:    {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 10,
    },
    searchBox:    {
      flex: 1,
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      borderRadius: SIZES.inputRadius,
      paddingHorizontal: 12,
      paddingVertical: 10,
      borderWidth: 1,
    },
    searchInput:  { flex: 1, fontSize: 14, padding: 0 },
    searchBtn:    {
      borderRadius: SIZES.inputRadius,
      width: 42, height: 42,
      alignItems: 'center', justifyContent: 'center',
    },

    /* VIN Banner */
    vinBanner:    {
      marginHorizontal: PADDING,
      marginTop: 14,
      borderRadius: SIZES.cardRadius,
      overflow: 'hidden',
    },
    vinContent:   {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 12,
      padding: 16,
    },
    vinTitle:     { fontSize: 15, fontWeight: '800', color: '#fff' },
    vinSub:       { fontSize: 12, color: 'rgba(255,255,255,0.8)', marginTop: 2 },
    vinArrow:     {
      width: 32, height: 32, borderRadius: 16,
      backgroundColor: '#fff',
      alignItems: 'center', justifyContent: 'center',
    },

    /* Sections */
    section:      { marginTop: 20, paddingHorizontal: PADDING },
    sectionHeader:{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
    sectionTitle: { fontSize: 17, fontWeight: '700' },
    seeAll:       { fontSize: 13, fontWeight: '500' },

    /* Grid rows */
    gridRow: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      marginBottom: CARD_GAP,
    },
  });
}

function chunk(arr, size) {
  const result = [];
  for (let i = 0; i < arr.length; i += size) result.push(arr.slice(i, i + size));
  return result;
}
