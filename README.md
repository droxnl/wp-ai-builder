<div align="center">

# WP AI Builder

**AI-powered WordPress website builder that turns structured instructions into full pages and a ready-to-use theme.**

[![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![OpenAI](https://img.shields.io/badge/OpenAI-API-412991?logo=openai&logoColor=white)](https://platform.openai.com/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-3DA639)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

</div>

---

## ✨ What is WP AI Builder?

WP AI Builder is a WordPress plugin that helps you create a complete website from a concise creative brief. Provide your sector, brand colors, logo, and page list; the plugin generates AI-written pages and automatically creates a lightweight theme to match your brand.

---

## ✅ Key Features

- **Admin UI for briefs**: Enter sector, site type, brand colors, logo URL, and pages.
- **AI-powered preview**: Generate a homepage preview before building the full site.
- **Automated page creation**: Creates WordPress pages from your page list.
- **Theme generation**: Builds a clean, minimal theme with your colors and logo.
- **OpenAI model selection**: Configure the model used for generation.

---

## 🧰 Requirements

- WordPress 6.x+
- PHP 7.4+
- An OpenAI API key

---

## 🚀 Getting Started

1. **Install the plugin**
   - Copy the plugin folder into `wp-content/plugins/wp-ai-builder`.
2. **Activate**
   - In the WordPress admin, go to **Plugins → Installed Plugins** and activate **WP AI Builder**.
3. **Open the builder**
   - Navigate to **AI Website Builder** in the admin menu.
4. **Configure your API key**
   - Add your OpenAI API key and choose a model.
5. **Generate**
   - Create a preview, then build the full site when you’re ready.

---

## 📝 How It Works

1. You provide a structured brief (sector, site type, colors, logo, pages, notes).
2. The plugin requests a preview from OpenAI for the homepage layout.
3. When you build, the plugin creates WordPress pages for each entry.
4. A lightweight theme is generated and activated automatically.

---

## ⚙️ Configuration Notes

- **Brand colors**: Use a comma-separated list like `#0f172a, #38bdf8`.
- **Pages**: Provide a comma-separated list like `Home, About, Services, Contact`.
- **Logo**: Use a public image URL for the site header.

---

## 🔐 Security & Privacy

- API keys are stored in WordPress settings.
- Requests are made server-side via WordPress AJAX.
- Only sanitized inputs are used for prompt generation.

---

## 🧪 Development

This project is a WordPress plugin with a small Vue-powered admin interface. Assets live in `assets/`, and PHP classes in `includes/`.

---

## 📌 Roadmap Ideas

- Theme templates beyond the home page
- Gutenberg block layout support
- Theme export and versioning
- Saved brief presets

---

## 📄 License

GPL-2.0+ — see the plugin header for details.

---

<div align="center">

**Built for creators who want a fast, beautiful WordPress site — without the busywork.**

</div>
