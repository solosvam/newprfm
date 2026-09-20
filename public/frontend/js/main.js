



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
window.showCartSuccess=function(){const notice=document.getElementById("cartSuccessNotice");if(!notice)return;notice.classList.add("is-visible");clearTimeout(window.cartSuccessTimer);window.cartSuccessTimer=setTimeout(()=>notice.classList.remove("is-visible"),4000)}
document.addEventListener("DOMContentLoaded",()=>updateHeaderCartCount());
window.addEventListener("parfumshop:cart-updated",event=>updateHeaderCartCount(event.detail));
window.addEventListener("storage",event=>{if(event.key==="parfumshop_cart")updateHeaderCartCount()});
