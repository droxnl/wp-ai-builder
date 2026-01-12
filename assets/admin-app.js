const { createApp } = Vue;

const sectorOptions = [
  {
    label: 'Zakelijke dienstverlening',
    value: 'Zakelijke dienstverlening',
    subsectors: ['Consultancy', 'Financiële dienstverlening', 'HR & Recruitment', 'Juridisch', 'Marketing & Communicatie', 'IT & Software', 'Logistiek', 'Vastgoed']
  },
  {
    label: 'Bouw & Vastgoed',
    value: 'Bouw & Vastgoed',
    subsectors: ['Aannemerij', 'Architectuur', 'Projectontwikkeling', 'Interieur', 'Makelaardij', 'Installatietechniek']
  },
  {
    label: 'Zorg & Welzijn',
    value: 'Zorg & Welzijn',
    subsectors: ['Tandartspraktijk', 'Fysiotherapie', 'Gezondheidscentrum', 'Thuiszorg', 'Psychologie', 'Wellness & Spa']
  },
  {
    label: 'Retail & E-commerce',
    value: 'Retail & E-commerce',
    subsectors: ['Mode', 'Elektronica', 'Wonen & Lifestyle', 'Beauty', 'Sport', 'Webshop']
  },
  {
    label: 'Horeca & Reizen',
    value: 'Horeca & Reizen',
    subsectors: ['Restaurant', 'Hotel', 'Catering', 'Toerisme', 'Reisbureau', 'Evenementenlocatie']
  },
  {
    label: 'Creatief & Media',
    value: 'Creatief & Media',
    subsectors: ['Designstudio', 'Fotografie', 'Videoproductie', 'Marketingbureau', 'Content creators', 'Muziek']
  },
  {
    label: 'Onderwijs',
    value: 'Onderwijs',
    subsectors: ['Basisschool', 'Voortgezet onderwijs', 'HBO/Universiteit', 'Trainingen & Coaching', 'E-learning']
  },
  {
    label: 'Overheid & Non-profit',
    value: 'Overheid & Non-profit',
    subsectors: ['Gemeente', 'Stichting', 'Vereniging', 'Goede doelen', 'Sportclub']
  },
  {
    label: 'Industrie & Productie',
    value: 'Industrie & Productie',
    subsectors: ['Maakindustrie', 'Techniek', 'Automotive', 'Energie', 'Agri & Food']
  },
  {
    label: 'Fintech & SaaS',
    value: 'Fintech & SaaS',
    subsectors: ['Fintech', 'SaaS', 'Cybersecurity', 'AI & Data', 'DevOps', 'Productiviteit']
  },
  {
    label: 'Lifestyle & Persoonlijk',
    value: 'Lifestyle & Persoonlijk',
    subsectors: ['Personal branding', 'Coaching', 'Fitness', 'Beautysalon', 'Yoga & Mindfulness']
  },
  {
    label: 'Andere',
    value: 'Andere',
    subsectors: []
  }
];

createApp({
  data() {
    return {
      step: 1,
      isBusy: false,
      isSuggesting: false,
      isPromptBusy: false,
      status: '',
      statusType: 'info',
      sectors: sectorOptions,
      logs: [],
      isLogBusy: false,
      isCleaning: false,
      brief: {
        sector: '',
        subsector: '',
        customSector: '',
        customSubsector: '',
        siteType: '',
        pages: '',
        notes: '',
        colors: ['#0f172a', '#38bdf8'],
        logoUrl: '',
        logoFile: null,
        logoPreview: '',
        promptMode: 'auto',
        customPrompt: '',
      },
      previewHtml: wpAiBuilderSettings.preview || '',
    };
  },
  computed: {
    hasPreview() {
      return this.previewHtml && this.previewHtml.length > 0;
    },
    availableSubsectors() {
      const match = this.sectors.find((sector) => sector.value === this.brief.sector);
      return match ? match.subsectors : [];
    },
    finalSector() {
      if (this.brief.sector === 'Andere') {
        return this.brief.customSector.trim();
      }
      return this.brief.sector;
    },
    finalSubsector() {
      if (this.brief.sector === 'Andere') {
        return this.brief.customSubsector.trim();
      }
      return this.brief.subsector;
    },
    needsCustomSector() {
      return this.brief.sector === 'Andere';
    },
    hasApiKey() {
      return wpAiBuilderSettings.hasApiKey;
    },
    hasPexelsKey() {
      return wpAiBuilderSettings.hasPexelsKey;
    },
  },
  mounted() {
    this.fetchLogs();
  },
  methods: {
    setStatus(message, type = 'info') {
      this.status = message;
      this.statusType = type;
    },
    addColor() {
      this.brief.colors.push('#ffffff');
    },
    removeColor(index) {
      if (this.brief.colors.length <= 1) {
        return;
      }
      this.brief.colors.splice(index, 1);
    },
    handleLogoFile(event) {
      const file = event.target.files[0];
      if (!file) {
        return;
      }
      this.brief.logoFile = file;
      this.brief.logoPreview = URL.createObjectURL(file);
    },
    clearLogoFile() {
      this.brief.logoFile = null;
      this.brief.logoPreview = '';
    },
    buildPayload(action) {
      const payload = new FormData();
      payload.append('action', action);
      payload.append('nonce', wpAiBuilderSettings.nonce);
      payload.append('sector', this.finalSector);
      payload.append('subsector', this.finalSubsector);
      payload.append('logoUrl', this.brief.logoUrl);
      payload.append('colors', this.brief.colors.filter(Boolean).join(', '));
      payload.append('siteType', this.brief.siteType);
      payload.append('pages', this.brief.pages);
      payload.append('notes', this.brief.notes);
      payload.append('promptMode', this.brief.promptMode);
      payload.append('customPrompt', this.brief.customPrompt);
      if (this.brief.logoFile) {
        payload.append('logoFile', this.brief.logoFile);
      }
      return payload;
    },
    async submit(action) {
      this.isBusy = true;
      this.setStatus(action === 'wp_ai_builder_preview' ? 'Preview aan het genereren...' : 'Website aan het bouwen...', 'info');

      const payload = this.buildPayload(action);

      try {
        const response = await fetch(wpAiBuilderSettings.ajaxUrl, {
          method: 'POST',
          body: payload,
        });
        const result = await response.json();
        if (!result.success) {
          this.setStatus(result.data?.message || 'Er ging iets mis.', 'error');
          return;
        }

        if (action === 'wp_ai_builder_preview') {
          this.previewHtml = result.data.html;
          this.logs = result.data.logs || this.logs;
          this.step = 4;
          this.setStatus('Preview klaar. Controleer en keur goed om de website te bouwen.', 'success');
          return;
        }

        const pages = result.data.pages?.join(', ') || 'Pagina’s aangemaakt.';
        this.setStatus(`Website succesvol gebouwd. ${pages}`, 'success');
        this.logs = result.data.logs || this.logs;
        this.step = 4;
      } catch (error) {
        this.setStatus('Netwerkfout. Probeer opnieuw.', 'error');
      } finally {
        this.isBusy = false;
      }
    },
    generatePreview() {
      this.submit('wp_ai_builder_preview');
    },
    approveBuild() {
      this.submit('wp_ai_builder_build');
    },
    goToStep(step) {
      this.step = step;
    },
    async requestSuggestions() {
      this.isSuggesting = true;
      this.setStatus('Suggesties worden opgehaald...', 'info');

      const payload = this.buildPayload('wp_ai_builder_suggest');

      try {
        const response = await fetch(wpAiBuilderSettings.ajaxUrl, {
          method: 'POST',
          body: payload,
        });
        const result = await response.json();
        if (!result.success) {
          this.setStatus(result.data?.message || 'Kon geen suggesties ophalen.', 'error');
          return;
        }

        if (result.data.siteType) {
          this.brief.siteType = result.data.siteType;
        }
        if (result.data.pages) {
          this.brief.pages = result.data.pages;
        }
        if (result.data.notes) {
          this.brief.notes = result.data.notes;
        }
        if (Array.isArray(result.data.colors)) {
          this.brief.colors = result.data.colors.length ? result.data.colors : this.brief.colors;
        }

        this.logs = result.data.logs || this.logs;
        this.setStatus('Suggesties toegevoegd. Pas ze gerust aan.', 'success');
      } catch (error) {
        this.setStatus('Netwerkfout. Probeer opnieuw.', 'error');
      } finally {
        this.isSuggesting = false;
      }
    },
    async requestPrompt() {
      this.isPromptBusy = true;
      this.setStatus('Uitgebreide prompt wordt gegenereerd...', 'info');

      const payload = this.buildPayload('wp_ai_builder_prompt');

      try {
        const response = await fetch(wpAiBuilderSettings.ajaxUrl, {
          method: 'POST',
          body: payload,
        });
        const result = await response.json();
        if (!result.success) {
          this.setStatus(result.data?.message || 'Kon geen prompt genereren.', 'error');
          return;
        }

        this.brief.customPrompt = result.data.prompt || '';
        this.brief.promptMode = 'custom';
        this.logs = result.data.logs || this.logs;
        this.setStatus('Prompt toegevoegd. Je kunt hem nog aanpassen.', 'success');
      } catch (error) {
        this.setStatus('Netwerkfout. Probeer opnieuw.', 'error');
      } finally {
        this.isPromptBusy = false;
      }
    },
    async fetchLogs() {
      this.isLogBusy = true;
      const payload = new FormData();
      payload.append('action', 'wp_ai_builder_logs');
      payload.append('nonce', wpAiBuilderSettings.nonce);

      try {
        const response = await fetch(wpAiBuilderSettings.ajaxUrl, {
          method: 'POST',
          body: payload,
        });
        const result = await response.json();
        if (result.success) {
          this.logs = result.data.logs || [];
        }
      } catch (error) {
        this.setStatus('Kon logboek niet ophalen.', 'error');
      } finally {
        this.isLogBusy = false;
      }
    },
    async cleanupGenerated() {
      this.isCleaning = true;
      this.setStatus('Opschonen van gegenereerde content...', 'info');

      const payload = new FormData();
      payload.append('action', 'wp_ai_builder_cleanup');
      payload.append('nonce', wpAiBuilderSettings.nonce);

      try {
        const response = await fetch(wpAiBuilderSettings.ajaxUrl, {
          method: 'POST',
          body: payload,
        });
        const result = await response.json();
        if (!result.success) {
          this.setStatus(result.data?.message || 'Opschonen mislukt.', 'error');
          return;
        }
        this.logs = result.data.logs || this.logs;
        this.setStatus(result.data?.message || 'Opschonen afgerond.', 'success');
      } catch (error) {
        this.setStatus('Netwerkfout. Probeer opnieuw.', 'error');
      } finally {
        this.isCleaning = false;
      }
    },
  },
  template: `
    <div class="ai-builder">
      <header class="ai-builder__header">
        <div>
          <p class="ai-builder__eyebrow">Website wizard</p>
          <h1>Professionele WordPress websites in duidelijke stappen</h1>
          <p class="ai-builder__subheading">Beantwoord stap voor stap de vragen, bekijk een premium preview en bouw direct een complete site met WPBakery-ondersteuning.</p>
        </div>
        <div class="ai-builder__steps">
          <button type="button" :class="['step-pill', step === 1 ? 'is-active' : '']" @click="goToStep(1)">1. Bedrijf</button>
          <button type="button" :class="['step-pill', step === 2 ? 'is-active' : '']" @click="goToStep(2)">2. Branding</button>
          <button type="button" :class="['step-pill', step === 3 ? 'is-active' : '']" @click="goToStep(3)">3. Content</button>
          <button type="button" :class="['step-pill', step === 4 ? 'is-active' : '']" @click="goToStep(4)">4. Preview</button>
        </div>
      </header>

      <section class="ai-builder__card" v-if="!hasApiKey">
        <div class="ai-builder__section">
          <div class="ai-builder__status error">OpenAI API key ontbreekt. Voeg deze toe bij Instellingen om te kunnen genereren.</div>
        </div>
      </section>

      <section class="ai-builder__card" v-if="step === 1">
        <div class="ai-builder__section">
          <h2>Stap 1 — Basisinformatie</h2>
          <p class="muted">Vertel ons in welke sector jouw klant opereert. We gebruiken dit voor structuur, beelden en tone of voice.</p>
          <div class="grid grid--2">
            <div>
              <label>Sector</label>
              <select v-model="brief.sector">
                <option disabled value="">Selecteer een sector</option>
                <option v-for="sector in sectors" :key="sector.value" :value="sector.value">{{ sector.label }}</option>
              </select>
            </div>
            <div v-if="availableSubsectors.length">
              <label>Subsector</label>
              <select v-model="brief.subsector">
                <option disabled value="">Selecteer een subsector</option>
                <option v-for="subsector in availableSubsectors" :key="subsector" :value="subsector">{{ subsector }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid--2" v-if="needsCustomSector">
            <div>
              <label>Custom sector</label>
              <input v-model="brief.customSector" type="text" placeholder="Beschrijf de sector" />
            </div>
            <div>
              <label>Custom subsector</label>
              <input v-model="brief.customSubsector" type="text" placeholder="Beschrijf de subsector" />
            </div>
          </div>
          <div class="grid grid--2">
            <div>
              <label>Website type</label>
              <input v-model="brief.siteType" type="text" placeholder="Marketing site, SaaS, Portfolio" />
            </div>
            <div>
              <label>Gewenste pagina's</label>
              <input v-model="brief.pages" type="text" placeholder="Home, Over ons, Diensten, Contact" />
            </div>
          </div>
          <div class="ai-builder__actions">
            <button class="btn btn--ghost" :disabled="isSuggesting || !hasApiKey" @click.prevent="requestSuggestions">AI suggesties ophalen</button>
            <button class="btn btn--primary" @click.prevent="goToStep(2)">Verder naar branding</button>
          </div>
        </div>
      </section>

      <section class="ai-builder__card" v-if="step === 2">
        <div class="ai-builder__section">
          <h2>Stap 2 — Branding & Logo</h2>
          <p class="muted">Upload een logo of geef een URL, en kies meerdere brand kleuren.</p>
          <div class="grid grid--2">
            <div>
              <label>Logo uploaden</label>
              <input type="file" accept="image/*" @change="handleLogoFile" />
              <div v-if="brief.logoPreview" class="ai-builder__logo-preview">
                <img :src="brief.logoPreview" alt="Logo preview" />
                <button class="btn btn--ghost" @click.prevent="clearLogoFile">Verwijderen</button>
              </div>
            </div>
            <div>
              <label>Logo URL</label>
              <input v-model="brief.logoUrl" type="url" placeholder="https://voorbeeld.nl/logo.svg" />
            </div>
          </div>
          <div class="ai-builder__colors">
            <label>Brand kleuren</label>
            <div class="color-list">
              <div class="color-item" v-for="(color, index) in brief.colors" :key="index">
                <input type="color" v-model="brief.colors[index]" />
                <input type="text" v-model="brief.colors[index]" placeholder="#0f172a" />
                <button class="btn btn--ghost" @click.prevent="removeColor(index)">Verwijder</button>
              </div>
            </div>
            <button class="btn btn--ghost" @click.prevent="addColor">Kleur toevoegen</button>
          </div>
          <div class="ai-builder__actions">
            <button class="btn btn--ghost" @click.prevent="goToStep(1)">Terug</button>
            <button class="btn btn--primary" @click.prevent="goToStep(3)">Verder naar content</button>
          </div>
        </div>
      </section>

      <section class="ai-builder__card" v-if="step === 3">
        <div class="ai-builder__section">
          <h2>Stap 3 — Content & briefing</h2>
          <p class="muted">Stel extra wensen op of laat de AI een uitgebreide briefing genereren.</p>
          <label>Extra instructies</label>
          <textarea v-model="brief.notes" rows="4" placeholder="Tone of voice, doelgroep, USP's, layout ideeën."></textarea>

          <div class="ai-builder__prompt">
            <label>Uitgebreide prompt</label>
            <div class="ai-builder__prompt-options">
              <label><input type="radio" value="auto" v-model="brief.promptMode" /> Automatisch op basis van de antwoorden</label>
              <label><input type="radio" value="custom" v-model="brief.promptMode" /> Eigen prompt gebruiken</label>
            </div>
            <textarea v-model="brief.customPrompt" rows="5" :disabled="brief.promptMode !== 'custom'" placeholder="Schrijf een uitgebreide prompt voor de AI."></textarea>
            <div class="ai-builder__actions">
              <button class="btn btn--ghost" :disabled="isPromptBusy || !hasApiKey" @click.prevent="requestPrompt">AI prompt genereren</button>
            </div>
          </div>

          <div class="ai-builder__notice" v-if="!hasPexelsKey">
            <strong>Tip:</strong> Voeg een Pexels API key toe in Instellingen om automatisch hoogwaardige beelden te gebruiken.
          </div>

          <div class="ai-builder__actions">
            <button class="btn btn--ghost" @click.prevent="goToStep(2)">Terug</button>
            <button class="btn btn--primary" :disabled="isBusy" @click.prevent="generatePreview">Premium preview genereren</button>
          </div>
        </div>
      </section>

      <section class="ai-builder__card" v-if="step === 4">
        <div class="ai-builder__section">
          <div class="ai-builder__preview-header">
            <div>
              <h2>Premium preview</h2>
              <p class="muted">Controleer de opzet, afbeeldingen en tone of voice voordat je bouwt.</p>
            </div>
            <button class="btn btn--primary" :disabled="isBusy" @click.prevent="approveBuild">Website bouwen</button>
          </div>
          <div class="ai-builder__preview" v-html="previewHtml"></div>
          <div class="ai-builder__actions">
            <button class="btn btn--ghost" @click.prevent="goToStep(3)">Terug naar content</button>
          </div>
        </div>
      </section>

      <section class="ai-builder__card" v-if="status">
        <div class="ai-builder__section">
          <div :class="['ai-builder__status', statusType]">
            <span v-if="isBusy || isSuggesting || isPromptBusy || isCleaning" class="ai-builder__loader"></span>
            {{ status }}
          </div>
        </div>
      </section>

      <section class="ai-builder__card">
        <div class="ai-builder__section">
          <div class="ai-builder__log-header">
            <div>
              <h2>Logboek</h2>
              <p class="muted">Bekijk alle stappen die de wizard uitvoert tijdens het bouwen.</p>
            </div>
            <div class="ai-builder__actions">
              <button class="btn btn--ghost" :disabled="isLogBusy" @click.prevent="fetchLogs">Logboek verversen</button>
              <button class="btn btn--ghost" :disabled="isCleaning" @click.prevent="cleanupGenerated">Verwijder alle gegenereerde content</button>
            </div>
          </div>
          <div class="ai-builder__log">
            <div v-if="!logs.length" class="muted">Nog geen logregels.</div>
            <ul v-else>
              <li v-for="(log, index) in logs" :key="index">
                <span class="log-time">{{ log.time }}</span>
                <span class="log-message">{{ log.message }}</span>
              </li>
            </ul>
          </div>
        </div>
      </section>
    </div>
  `,
}).mount('#wp-ai-builder-app');
