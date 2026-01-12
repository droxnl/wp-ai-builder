const { createApp } = Vue;

createApp({
  data() {
    return {
      step: 1,
      isBusy: false,
      status: '',
      statusType: 'info',
      settings: {
        apiKey: '',
        model: 'gpt-4o-mini',
      },
      brief: {
        sector: '',
        logo: '',
        colors: '',
        siteType: '',
        pages: '',
        notes: '',
      },
      previewHtml: wpAiBuilderSettings.preview || '',
    };
  },
  mounted() {
    const container = document.getElementById('wp-ai-builder-app');
    this.settings.apiKey = container.dataset.apiKey || '';
    this.settings.model = container.dataset.model || 'gpt-4o-mini';
  },
  computed: {
    hasPreview() {
      return this.previewHtml && this.previewHtml.length > 0;
    },
  },
  methods: {
    setStatus(message, type = 'info') {
      this.status = message;
      this.statusType = type;
    },
    async submit(action) {
      this.isBusy = true;
      this.setStatus(action === 'wp_ai_builder_preview' ? 'Generating preview...' : 'Building website...', 'info');

      const payload = new URLSearchParams({
        action,
        nonce: wpAiBuilderSettings.nonce,
        sector: this.brief.sector,
        logo: this.brief.logo,
        colors: this.brief.colors,
        siteType: this.brief.siteType,
        pages: this.brief.pages,
        notes: this.brief.notes,
      });

      try {
        const response = await fetch(wpAiBuilderSettings.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          },
          body: payload.toString(),
        });
        const result = await response.json();
        if (!result.success) {
          this.setStatus(result.data?.message || 'Something went wrong.', 'error');
          return;
        }

        if (action === 'wp_ai_builder_preview') {
          this.previewHtml = result.data.html;
          this.step = 2;
          this.setStatus('Preview ready. Review and approve to build the site.', 'success');
          return;
        }

        const pages = result.data.pages?.join(', ') || 'Pages created.';
        this.setStatus(`Website built successfully. ${pages}`, 'success');
        this.step = 3;
      } catch (error) {
        this.setStatus('Network error. Please try again.', 'error');
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
  },
  template: `
    <div class="ai-builder">
      <header class="ai-builder__header">
        <div>
          <p class="ai-builder__eyebrow">AI Website Builder</p>
          <h1>Create premium WordPress sites in minutes</h1>
          <p class="ai-builder__subheading">Feed the AI a clear brief, preview the experience, and approve a full build with theme, pages, and styling.</p>
        </div>
        <div class="ai-builder__steps">
          <div :class="['step-pill', step === 1 ? 'is-active' : '']">1. Brief</div>
          <div :class="['step-pill', step === 2 ? 'is-active' : '']">2. Preview</div>
          <div :class="['step-pill', step === 3 ? 'is-active' : '']">3. Build</div>
        </div>
      </header>

      <section class="ai-builder__card">
        <div class="ai-builder__section">
          <h2>OpenAI Configuration</h2>
          <p class="muted">Securely store the API credentials needed to generate the content.</p>
          <div class="grid grid--2">
            <div>
              <label>OpenAI API Key</label>
              <input v-model="settings.apiKey" type="password" name="wp_ai_builder_settings[api_key]" form="wp-ai-builder-settings" placeholder="sk-..." />
            </div>
            <div>
              <label>Model</label>
              <input v-model="settings.model" type="text" name="wp_ai_builder_settings[model]" form="wp-ai-builder-settings" placeholder="gpt-4o-mini" />
            </div>
          </div>
          <form id="wp-ai-builder-settings" method="post" action="options.php">
            ${wpAiBuilderSettings.settingsFields || ''}
            <button type="submit" class="btn btn--ghost">Save Configuration</button>
          </form>
        </div>
      </section>

      <section class="ai-builder__card">
        <div class="ai-builder__section">
          <h2>Website Brief</h2>
          <p class="muted">Describe the brand and let the AI draft the experience.</p>
          <div class="grid grid--2">
            <div>
              <label>Sector / Industry</label>
              <input v-model="brief.sector" type="text" placeholder="Fintech, Wellness, Real Estate" />
            </div>
            <div>
              <label>Website Type</label>
              <input v-model="brief.siteType" type="text" placeholder="Marketing, SaaS, Portfolio" />
            </div>
            <div>
              <label>Logo URL</label>
              <input v-model="brief.logo" type="url" placeholder="https://example.com/logo.svg" />
            </div>
            <div>
              <label>Brand Colors</label>
              <input v-model="brief.colors" type="text" placeholder="#0f172a, #38bdf8" />
            </div>
          </div>
          <div class="grid">
            <div>
              <label>Desired Pages (comma separated)</label>
              <input v-model="brief.pages" type="text" placeholder="Home, About, Services, Contact" />
            </div>
          </div>
          <label>Extra Instructions</label>
          <textarea v-model="brief.notes" rows="5" placeholder="Describe tone, layout ideas, audience, and goals."></textarea>
          <div class="ai-builder__actions">
            <button class="btn btn--primary" :disabled="isBusy" @click.prevent="generatePreview">Generate Preview</button>
            <button class="btn btn--ghost" :disabled="isBusy" @click.prevent="goToStep(2)" v-if="hasPreview">Skip to Preview</button>
          </div>
        </div>
      </section>

      <section class="ai-builder__card" v-if="step >= 2">
        <div class="ai-builder__section">
          <div class="ai-builder__preview-header">
            <div>
              <h2>Live Preview</h2>
              <p class="muted">Review the AI-generated draft before building the full site.</p>
            </div>
            <button class="btn btn--primary" :disabled="isBusy" @click.prevent="approveBuild">Approve &amp; Build Website</button>
          </div>
          <div class="ai-builder__preview" v-html="previewHtml"></div>
        </div>
      </section>

      <section class="ai-builder__card" v-if="status">
        <div class="ai-builder__section">
          <div :class="['ai-builder__status', statusType]">{{ status }}</div>
        </div>
      </section>
    </div>
  `,
}).mount('#wp-ai-builder-app');
