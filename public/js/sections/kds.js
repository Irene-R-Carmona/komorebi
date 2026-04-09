document.addEventListener('alpine:init', () => {
  Alpine.data('kdsApp', () => ({
    sopOpen: false,
    sopData: {
      title: '', station: '', ingred: [], steps: [], check: '', allergens: []
    },

    // Helper para normalizar listas (ingredientes, alérgenos, etc.)
    parseList(list) {
      if (Array.isArray(list)) {
        return list;
      }

      if (list == null || list === '') {
        return [];
      }

      try {
        const parsed = typeof list === 'string' ? JSON.parse(list) : list;
        return Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        console.warn('Failed to parse list:', e);
        return [];
      }
    },

    // Helper para parsear pasos (si es string con \n)
    parseSteps(text) {
      if (!text) return [];
      const normalized = text.replaceAll('\r\n', '\n');
      return normalized.split('\n').filter(s => s.trim().length > 0);
    },

    openSop(data) {
      console.log("Opening SOP", data);

      this.sopData = {
        title: data.title,
        station: data.station || 'General',
        ingred: this.parseList(data.ingred),
        steps: this.parseSteps(data.steps),
        check: data.check,
        allergens: this.parseList(data.allergens)
          .map(s => s.trim())
          .filter(s => s.length > 0),
      };

      this.sopOpen = true;
    },
  }))
})
