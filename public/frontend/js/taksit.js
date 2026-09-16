
const taksitMonths = document.querySelectorAll('.taksit .months li')

for(let month of taksitMonths) {
  month.onclick = function() {
    let active = document.querySelector('.active-taksit')
    active.classList.remove('active-taksit')
    
    this.classList.add('active-taksit')
  }
}