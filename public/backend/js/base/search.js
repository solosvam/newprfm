/**
 *
 * Search
 * Website generic search. Customized version of autoComplete plugin to work within the modal. Edit this class according to your project needs.
 *
 * @param {string} searchModalId Id of the search modal
 * @param {string} searchInputId Id of the input to init autocomplete
 * @param {string} searchResultsId Id to give created result element
 * @param {string} placeholder Placeholder text
 * @param {string} loading Placeholder text for loading json data
 * @param {string} jsonPath Path to json data
 *
 */

class Search {
  get options() {
    return {
      searchModalId: 'searchPagesModal',
      searchInputId: 'searchPagesInput',
      searchResultsId: 'searchPagesResults',
      placeholder: 'Axtar',
      loading: 'Yüklənir...',
      jsonPath: '/admin/search-pages',
    };
  }

  constructor(options = {}) {
    this.settings = Object.assign(this.options, options);
    if (!document.getElementById(this.settings.searchInputId)) {
      return null;
    }
    this._addListeners();
    this._init();
    return this._autoComplete;
  }

  _init() {
    const searchInput = document.getElementById(this.settings.searchInputId);
    this._autoComplete = new autoComplete({
      data: {
        src: async () => {
          searchInput.setAttribute('placeholder', this.settings.loading);
          const source = await fetch(this.settings.jsonPath, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}});
          if (!source.ok) throw new Error('Axtarış siyahısı yüklənmədi');
          const data = await source.json();
          searchInput.setAttribute('placeholder', this.settings.placeholder);
          return data;
        },
        key: ['label'],
        cache: false,
      },
      // Uyğunluq: söz başlanğıcında tapılan və qısa ad yuxarıda
      sort: (a, b) => Search.score(this._query, a.value.label) - Search.score(this._query, b.value.label),
      placeHolder: this.settings.placeholder,
      selector: '#' + this.settings.searchInputId,
      threshold: 1,
      debounce: 0,
      // "loose" hərfləri ardıcıl, aralarında başqa hərflərlə axtarırdı ("kart" → "KAtaloq > AxtaRış idarəeTməsi").
      // İndi: yazılan hər söz adın içində bütöv parça kimi olmalıdır; ə/e, ı/i, ş/s... fərq etmir; tapılan hissə işıqlanır.
      searchEngine: (query, record) => {
        this._query = query;
        return Search.match(query, record);
      },
      maxResults: 10,
      resultsList: {
        render: true,
        container: (source) => {
          source.setAttribute('id', this.settings.searchResultsId);
          source.setAttribute('class', 'auto-complete-result');
        },
        destination: document.getElementById(this.settings.searchInputId),
        position: 'afterend',
        element: 'ul',
      },
      resultItem: {
        content: (data, source) => {
          source.innerHTML = '<p class="mb-0">' + data.match + '</p>' + '<p class="text-small text-muted mb-0">' + data.value.url + '</p>';
          source.setAttribute('class', 'auto-complete-result-item');
        },
        element: 'li',
      },
      noResults: () => {
        const result = document.createElement('li');
        result.setAttribute('class', 'no_resulst');
        result.setAttribute('tabindex', '1');
        result.textContent = 'Nəticə tapılmadı';
        document.getElementById(this.settings.searchResultsId).appendChild(result);
      },
      onSelection: (feedback) => {
        window.location.href = feedback.selection.value['url'];
        searchInput.value = '';
        searchInput.setAttribute('placeholder', this.settings.placeholder);
      },
    });
  }

  /** Müqayisə üçün: kiçik hərf, Azərbaycan hərfləri latın qarşılığına (hər hərf bir hərfə — mövqelər üst-üstə düşür) */
  static fold(text) {
    const map = {'ə': 'e', 'ı': 'i', 'İ': 'i', 'I': 'i', 'ö': 'o', 'ü': 'u', 'ş': 's', 'ç': 'c', 'ğ': 'g', 'Ə': 'e', 'Ö': 'o', 'Ü': 'u', 'Ş': 's', 'Ç': 'c', 'Ğ': 'g'};
    return Array.from(String(text), (ch) => map[ch] || ch.toLowerCase().charAt(0)).join('');
  }

  static words(query) {
    return Search.fold(query).split(/[\s>]+/).filter(Boolean);
  }

  /** Bütün sözlər tapılırsa — işıqlanmış HTML, yoxsa null */
  static match(query, record) {
    const words = Search.words(query);
    if (!words.length) return null;
    const label = Array.from(String(record));
    const folded = Search.fold(record);
    const marked = new Array(label.length).fill(false);
    for (const word of words) {
      const at = folded.indexOf(word);
      if (at < 0) return null;
      for (let i = at; i < at + word.length; i++) marked[i] = true;
    }
    const esc = (ch) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[ch] || ch);
    return label.map((ch, i) => {
      const open = marked[i] && !marked[i - 1] ? '<span class="autoComplete_highlighted">' : '';
      const close = marked[i] && !marked[i + 1] ? '</span>' : '';
      return open + esc(ch) + close;
    }).join('');
  }

  /** Kiçik — daha uyğun: söz başlanğıcında tapılma, sonra adın son hissəsində (səhifənin adı), sonra qısalıq */
  static score(query, label) {
    const folded = Search.fold(label);
    const page = folded.split('>').pop();
    let score = folded.length / 1000;
    for (const word of Search.words(query)) {
      const at = folded.indexOf(word);
      if (at > 0 && /[a-z0-9]/.test(folded[at - 1])) score += 10;   // sözün ortasında
      if (!page.includes(word)) score += 5;                           // yalnız bölmənin adında
    }
    return score;
  }

  _addListeners() {
    document.getElementById(this.settings.searchModalId).addEventListener('shown.bs.modal', this._onSearchModalShown.bind(this));
    document.getElementById(this.settings.searchModalId).addEventListener('hidden.bs.modal', this._onSearchModalHidden.bind(this));
    document.getElementById(this.settings.searchInputId).addEventListener('focus', this._onInputFocus.bind(this));
    document.getElementById(this.settings.searchInputId).addEventListener('focusout', this._onInputFocusOut.bind(this));
  }

  // Focusing to input when modal is shown
  _onSearchModalShown() {
    document.getElementById(this.settings.searchInputId).focus();
  }

  // Clearing search results and input when modal is hidden
  _onSearchModalHidden() {
    document.getElementById(this.settings.searchInputId).value = '';
    const reuslts = document.getElementById(this.settings.searchResultsId);
    while (reuslts.firstChild) {
      reuslts.removeChild(reuslts.firstChild);
    }
  }

  _onInputFocus() {
    document.getElementById(this.settings.searchInputId).addEventListener('keydown', this._onKeyDown.bind(this));
  }

  _onInputFocusOut() {
    document.getElementById(this.settings.searchInputId).removeEventListener('keydown', this._onKeyDown.bind(this));
  }

  // Preventing up and down key events to go start and end of the input
  _onKeyDown(event) {
    if (event.which === 38 || event.which === 40) {
      event.preventDefault();
    }
  }
}
