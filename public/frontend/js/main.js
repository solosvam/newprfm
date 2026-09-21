



// function updateRangeValues() {
//     let minValue = document.getElementById('min-range').value;
//     let maxValue = document.getElementById('max-range').value;

//     if (parseInt(minValue) >= parseInt(maxValue)) {
//         minValue = maxValue - 1;
//         document.getElementById('min-range').value = minValue;
//     }

//     if (parseInt(maxValue) <= parseInt(minValue)) {
//         maxValue = parseInt(minValue) + 1;
//         document.getElementById('max-range').value = maxValue;
//     }

//     document.getElementById('min-value').textContent = minValue + " AZN";
//     document.getElementById('max-value').textContent = maxValue + " AZN";
// }




// // Terms modal tabmenu
// const modal_tabLinks = document.querySelectorAll('.modal-tablinks button')


// for(let tablink of modal_tabLinks) {
//     tablink.onclick = function() {
//         let active = document.querySelector('.modal-tab-active')
//         active.classList.remove('modal-tab-active')
//         this.classList.add('modal-tab-active')
//     }
// }



const heartIcon = document.querySelectorAll('.heart-icon');

for(let icon of heartIcon) {
    icon.addEventListener('click', () => {
        icon.classList.toggle('active');
      });
}

document.getElementById('languageSwitcher').addEventListener('change', function() {
    var newLang = this.value;
    var currentUrl = window.location.hostname;
    var mainDomain = 'parfumshop.test';
    var otherParts = window.location.pathname;

    var currentSub = currentUrl.split('.')[0];

    if(newLang === 'az'){
        window.location.href = `https://${mainDomain}${otherParts}`;
    }else{
        if(currentSub === 'en' || currentSub === 'ru') {
            window.location.href = `https://${newLang}.${mainDomain}${otherParts}`;
        } else {
            window.location.href = `https://${newLang}.${currentUrl}${otherParts}`;
        }
    }
});

function getParfumshopCart(){try{return JSON.parse(localStorage.getItem("parfumshop_cart")||"[]")}catch(e){return []}}
function updateHeaderCartCount(cart=getParfumshopCart()){const badge=document.getElementById("headerCartCount");if(!badge)return;const count=cart.reduce((sum,item)=>sum+(parseInt(item.quantity,10)||0),0);badge.textContent=count;badge.classList.toggle("is-empty",count===0)}
window.showCartSuccess=function(){if(!window.jQuery||!jQuery.notify)return;jQuery.notify.addStyle("parfumshop-success",{html:'<div><div class="ps-notify"><span class="ps-notify__check">✓</span><span data-notify-text></span></div></div>'});jQuery.notify("Məhsul səbətə əlavə olundu",{style:"parfumshop-success",className:"success",globalPosition:"top right",autoHideDelay:3000,showAnimation:"fadeIn",hideAnimation:"fadeOut"})}
document.addEventListener("DOMContentLoaded",()=>updateHeaderCartCount());
window.addEventListener("parfumshop:cart-updated",event=>updateHeaderCartCount(event.detail));
window.addEventListener("storage",event=>{if(event.key==="parfumshop_cart")updateHeaderCartCount()});


const FAVORITES_KEY = 'parfumshop_favorites';
function getLocalFavorites(){try{return [...new Set(JSON.parse(localStorage.getItem(FAVORITES_KEY)||'[]').map(Number).filter(Boolean))]}catch(e){return []}}
function setLocalFavorites(ids){localStorage.setItem(FAVORITES_KEY,JSON.stringify([...new Set(ids.map(Number).filter(Boolean))]))}
function paintFavorites(ids){const set=new Set(ids.map(Number));document.querySelectorAll('.favorite-toggle').forEach(btn=>{const active=set.has(Number(btn.dataset.productId));btn.classList.toggle('is-favorite',active);btn.setAttribute('aria-pressed',active?'true':'false')})}
function showFavoriteNotice(added){if(window.jQuery&&jQuery.notify){if(!jQuery.notify.getStyle('parfumshop-success'))jQuery.notify.addStyle('parfumshop-success',{html:'<div><div class="ps-notify"><span class="ps-notify__check">✓</span><span data-notify-text></span></div></div>'});jQuery.notify(added?'Bəyəndiyim ətirlərə əlavə olundu':'Bəyəndiyim ətirlərdən silindi',{style:'parfumshop-success',className:'success',globalPosition:'top right',autoHideDelay:2500,showAnimation:'fadeIn',hideAnimation:'fadeOut'})}}
async function favoriteRequest(url,method='GET',body=null){const cfg=window.parfumshopFavoriteConfig||{};const res=await fetch(url,{method,headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':cfg.csrf||''},body:body?JSON.stringify(body):null});if(!res.ok)throw new Error('Favorite request failed');return res.json()}
async function initFavorites(){
    const cfg=window.parfumshopFavoriteConfig||{};
    let local=getLocalFavorites();
    if(cfg.authenticated){
        if(local.length){const synced=await favoriteRequest(cfg.syncUrl,'POST',{product_ids:local});local=(synced.ids||[]).map(Number);localStorage.removeItem(FAVORITES_KEY)}
        else{const data=await favoriteRequest(cfg.idsUrl);local=(data.ids||[]).map(Number)}
    }
    paintFavorites(local);
}
document.addEventListener('click',async e=>{
    const btn=e.target.closest('.favorite-toggle'); if(!btn)return;
    e.preventDefault();e.stopPropagation();
    const id=Number(btn.dataset.productId),cfg=window.parfumshopFavoriteConfig||{};
    const active=btn.classList.contains('is-favorite');
    if(cfg.authenticated){
        try{await favoriteRequest(cfg.storeUrl+'/'+id,active?'DELETE':'POST');btn.classList.toggle('is-favorite',!active);btn.setAttribute('aria-pressed',!active?'true':'false');showFavoriteNotice(!active);if(active&&location.pathname.includes('/profile/wishlist'))btn.closest('.product-item')?.remove()}catch(e){}
    }else{
        let ids=getLocalFavorites();ids=active?ids.filter(x=>x!==id):[...ids,id];setLocalFavorites(ids);paintFavorites(ids);showFavoriteNotice(!active);
    }
});
document.addEventListener('click',e=>{const link=e.target.closest('.wishlist-page-link');const cfg=window.parfumshopFavoriteConfig||{};if(link&&!cfg.authenticated){e.preventDefault();const ids=getLocalFavorites();if(!ids.length){alert('Hələ bəyəndiyiniz ətir yoxdur.');return}document.querySelector('[data-product-id="'+ids[0]+'"]')?.scrollIntoView({behavior:'smooth',block:'center'})}});
document.addEventListener('DOMContentLoaded',()=>{initFavorites().catch(()=>{})});
