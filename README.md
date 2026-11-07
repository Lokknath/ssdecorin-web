# ssdecorin.com — Sai Sreenivasa Decorin

Official website for **Sai Sreenivasa Decorin** — Independent Interior Technical Services, Consultancy & Training.
Led by Managing Partner **S. S. Naga Nandan**.

* **Live Domain:** [https://ssdecorin.com](https://ssdecorin.com)
* **Hosting:** MilesWeb (LiteSpeed / Apache)
* **Created & Built by:** [Bilva](https://bilva.tech)

---

## Architecture & Structure

```
├── .gitignore
├── README.md
└── site/
    ├── index.html              # Main website
    ├── styles.css              # Midnight Navy & Gold custom design system
    ├── script.js               # Interactive interaction engine (a11y + 60/120fps)
    ├── send-enquiry.php        # Native PHP backend mailer for MilesWeb
    ├── .htaccess               # Apache/LiteSpeed security, HTTPS, compression & caching
    ├── robots.txt              # Search engine crawling rules
    ├── sitemap.xml             # XML sitemap for Google/Bing indexing
    ├── 404.html                # Branded 404 error page
    ├── deploy-milesweb.sh      # Automated SSH rsync deployment script
    └── assets/                 # Brand logos, portraits, icons & photography
```

---

## Deployment to MilesWeb

The project includes an automated deployment script reading credentials from `site/.env`:

```bash
# 1. Ensure site/.env is configured (template in site/.env.example):
milesweb_ssh=ssh -p 22 nsquare_1@45.199.139.26
milesweb_pass=YOUR_PASSWORD
REMOTE_PATH=site

# 2. Run the deployment script:
./site/deploy-milesweb.sh
```

Alternatively, upload `milesweb-deploy.zip` directly to your cPanel File Manager and extract.

---

## Features

- **High-Performance Graphics:** Batched canvas mesh rendering, hardware-accelerated 3D tilts, async portrait edge-detection lens, smooth marquee with WCAG 2.2.2 hover pause.
- **Accessible & Responsive:** Meets WCAG 2.1 AA standards with visible focus outlines and full `prefers-reduced-motion` support.
- **Robust Lead Pipeline:** Native backend email sending via `send-enquiry.php` with anti-spam honeypot, plus automatic WhatsApp and client-side fallbacks.
- **Security & Caching:** 2-year HSTS, Content-Security-Policy, 1-year immutable caching for static assets, and Gzip/Deflate compression.
