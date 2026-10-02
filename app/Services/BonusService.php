<?php
namespace App\Services;
use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Setting;

class BonusService {
 /**
  * Ayarlar → Bonuslar → "Bonusun istifadə müddəti": bonus növü (earn — sifariş, register — qeydiyyat) üzrə gün sayı.
  * null — müddətsiz. Referal bonusunun müddəti ReferralSettings-dədir.
  */
 public function expiryDays(string $type): ?int {
  if ((int) Setting::valueOf('bonus_expiry_enabled', 0) !== 1) return null;
  $key = ['earn' => 'bonus_order_expiry_days', 'register' => 'bonus_registration_expiry_days'][$type] ?? null;
  return $key ? max(1, (int) Setting::valueOf($key, $type === 'earn' ? 365 : 90)) : null;
 }

 /** Bonus şərtlərinin standart mətni (Ayarlar → Bonuslar → "Bonus şərtləri"); :percent, :registration əvəz olunur */
 public const TERMS_DEFAULTS = [
  'az' => "Bonus nədir?\nBonus Parfumshop.az-da alış-veriş edən müştərilərə verilən hədiyyə balansıdır. 1 bonus = 1 ₼.\n\nBonus necə qazanılır?\n• Hər sifarişdən məhsulların dəyərinin :percent%-i bonus olaraq hesabınıza yazılır (çatdırılma haqqı nəzərə alınmır).\n• Saytda qeydiyyatdan keçən yeni müştərilərə :registration ₼ qeydiyyat bonusu verilir.\n• Dostunuzu dəvət etdikdə, onun ilk sifarişi təhvil verildikdən sonra referal bonusu qazanırsınız.\n\nBonus necə istifadə olunur?\n• Sifariş zamanı ödəniş üsulu olaraq bonus balansını seçin.\n• Bonusla sifarişin müəyyən hissəsini ödəmək mümkündür; həddi sifariş səhifəsində göstərilir.\n\nVacib məlumat\n• Sifariş ləğv edildikdə və ya qaytarıldıqda həmin sifarişdən qazanılan bonus balansdan çıxılır.\n• Bonus nağd pula çevrilmir və başqa hesaba köçürülmür.\n• Parfumshop.az bonus şərtlərini dəyişmək hüququnu özündə saxlayır.",
  'en' => "What is a bonus?\nBonus is a reward balance for customers shopping at Parfumshop.az. 1 bonus = 1 ₼.\n\nHow to earn bonuses?\n• :percent% of the product value of every order is credited to your account as bonus (delivery fee excluded).\n• New customers who sign up on the website receive a :registration ₼ welcome bonus.\n• When you invite a friend, you earn a referral bonus after their first order is delivered.\n\nHow to use bonuses?\n• Choose bonus balance as the payment method when placing an order.\n• Bonuses can cover a part of the order; the limit is shown on the order page.\n\nImportant\n• If an order is cancelled or returned, the bonus earned from it is deducted from your balance.\n• Bonuses cannot be exchanged for cash or transferred to another account.\n• Parfumshop.az reserves the right to change the bonus terms.",
  'ru' => "Что такое бонус?\nБонус — это подарочный баланс для покупателей Parfumshop.az. 1 бонус = 1 ₼.\n\nКак получить бонусы?\n• С каждого заказа на ваш счёт начисляется :percent% от стоимости товаров (без учёта доставки).\n• Новые покупатели при регистрации на сайте получают :registration ₼ приветственного бонуса.\n• Пригласив друга, вы получаете реферальный бонус после доставки его первого заказа.\n\nКак использовать бонусы?\n• При оформлении заказа выберите оплату бонусным балансом.\n• Бонусами можно оплатить часть заказа; лимит указан на странице заказа.\n\nВажно\n• При отмене или возврате заказа начисленный за него бонус списывается с баланса.\n• Бонусы не обмениваются на деньги и не переводятся на другой счёт.\n• Parfumshop.az оставляет за собой право изменять условия бонусной программы.",
 ];

 /** Müştəriyə göstəriləcək bonus şərtləri (dəyərlər hazırkı ayarlarla əvəz olunur) */
 public function terms(?string $locale = null): string {
  $locale = array_key_exists((string) $locale, self::TERMS_DEFAULTS) ? $locale : 'az';
  $text = (string) (Setting::valueOf('bonus_terms_'.$locale) ?: self::TERMS_DEFAULTS[$locale]);
  $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
  return strtr($text, [
   ':percent' => $num(Setting::valueOf('order_bonus_percent', 5)),
   ':registration' => $num(Setting::valueOf('registration_bonus_amount', 10)),
  ]);
 }

 /**
  * Ayarlar → Bonuslar → "Bonusla ödəniş həddi": sifarişin ən çox neçə faizi bonusla ödənə bilər (bütün bonuslar).
  * null — hədd yoxdur (balans imkan verdikcə tam ödəmək olar).
  */
 public function payLimitPercent(): ?float {
  if ((int) Setting::valueOf('bonus_pay_limit_enabled', 0) !== 1) return null;
  return min(100, max(0, (float) Setting::valueOf('bonus_pay_percent', 30)));
 }

 /** Sifarişə düşən bonus: (məhsullar − endirim) × faiz. Ödənişdən əvvəl göstərmək üçün də (SMS ödəniş linki) */
 public function amountForOrder(Order $order): float {
  $percent=(float) Setting::valueOf('order_bonus_percent',5);
  $eligibleAmount = max(0, (float)$order->subtotal - (float)$order->discount);
  return round($eligibleAmount*($percent/100),2);
 }

 public function earnForOrder(Customer $customer, Order $order, float $paidAmount): float {
  $bonus=$this->amountForOrder($order);
  if($bonus<=0)return 0;
  $customer->increment('bonus_balance',$bonus);
  $customer->bonusTransactions()->create(['order_id'=>$order->id,'type'=>'earn','amount'=>$bonus,'note'=>'Sifariş bonusu']);
  $order->update(['bonus_earned'=>$bonus]);
  return $bonus;
 }

 /**
  * Qeydiyyat bonusu (Ayarlar → "Qeydiyyat bonusu"): saytda SMS kodu təsdiqlənəndə və Asan sifarişdə yeni müştəriyə.
  * Hər müştəriyə bir dəfə: artıq "register" qeydi varsa heç nə yazılmır. Yazılan məbləği qaytarır (0 — verilmədi).
  */
 public function grantRegistration(Customer $customer): float {
  if ((int) Setting::valueOf('registration_bonus_enabled', 1) !== 1) return 0;
  $amount = max(0, round((float) Setting::valueOf('registration_bonus_amount', 10), 2));
  if ($amount <= 0) return 0;

  return \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $amount) {
   // Eyni müştəriyə paralel iki sorğu iki bonus yazmasın
   \Illuminate\Support\Facades\DB::table('customers')->where('id', $customer->id)->lockForUpdate()->first();
   if ($customer->bonusTransactions()->where('type', 'register')->exists()) return 0;
   $customer->increment('bonus_balance', $amount);
   $customer->bonusTransactions()->create(['type' => 'register', 'amount' => $amount, 'note' => 'Qeydiyyat bonusu']);
   return $amount;
  });
 }
}
