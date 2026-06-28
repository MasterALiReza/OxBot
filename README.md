# 🤖 Ox Panel & OxBot

🇮🇷 **[برای مشاهده راهنمای فارسی، اینجا کلیک کنید (README_FA.md)](README_FA.md)**

---

<div align="center">

# 🤖 Ox Panel & OxBot

### A premium Telegram bot & Web Panel for selling VPN services — with fully automated config creation.

<p>
  <a href="https://t.me/OxVpN_Net">
    <img src="https://img.shields.io/badge/Telegram-Channel-2CA5E0?style=for-the-badge&logo=telegram&logoColor=white" alt="Telegram Channel"/>
  </a>
  <a href="https://t.me/WexortChat">
    <img src="https://img.shields.io/badge/Telegram-Group-229ED9?style=for-the-badge&logo=telegram&logoColor=white" alt="Telegram Group"/>
  </a>
</p>

<p>
  <a href="https://github.com/MasterALiReza/OxBot/stargazers">
    <img src="https://img.shields.io/github/stars/MasterALiReza/OxBot?style=flat-square&color=f5c518" alt="Stars"/>
  </a>
  <a href="https://github.com/MasterALiReza/OxBot/network/members">
    <img src="https://img.shields.io/github/forks/MasterALiReza/OxBot?style=flat-square" alt="Forks"/>
  </a>
  <a href="https://github.com/MasterALiReza/OxBot/issues">
    <img src="https://img.shields.io/github/issues/MasterALiReza/OxBot?style=flat-square" alt="Issues"/>
  </a>
  <a href="https://github.com/MasterALiReza/OxBot/blob/main/LICENSE">
    <img src="https://img.shields.io/github/license/MasterALiReza/OxBot?style=flat-square" alt="License"/>
  </a>
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2"/>
</p>

</div>

---

## 📚 Table of Contents

- [✨ Overview](#-overview)
- [🧩 Supported Panels](#-supported-panels)
- [💳 Payment Gateways](#-payment-gateways)
- [⚙️ Features](#-features)
- [🚀 Installation & Update](#-installation--update)
  - [Prerequisites](#prerequisites)
  - [Direct Installation](#-direct-installation-stable)
  - [Updating](#-updating)
- [🙏 Credits & Open Source](#-credits--open-source)

---

## ✨ Overview

**OxBot** (together with the powerful **Ox Web Panel**) is a comprehensive solution designed for selling and managing VPN services. 

It connects directly to your panels, builds configurations automatically, accepts a wide range of payment methods, and gives both customers and admins a clean experience through a **Telegram bot** and a **web admin panel**.

---

## 🧩 Supported Panels

OxBot integrates with the most popular VPN and network management panels:

| Panel | Panel |
|-------|-------|
| 🟢 **Marzban** | 🟢 **Marzneshin** |
| 🟢 **3x-ui** / **X-UI** | 🟢 **Hiddify** |
| 🟢 **WG-Dashboard** (WireGuard) | 🟢 **MikroTik** |
| 🟢 **IBSng** | 🟢 **Pasarguard** |
| 🟢 **Alireza Panel** | 🟢 **S-UI** |

> Configs are generated automatically and are compatible with all common protocols.

---

## 💳 Payment Gateways

| Gateway | Type |
|---------|------|
| 💵 **Card-to-Card** | Manual (receipt + admin approval) |
| 🪙 **NowPayments** | Crypto |
| 🇮🇷 **Aqayepardakht** | Online gateway |

---

## ⚙️ Features

### 🖥️ Web Admin Panel
- 📊 **Sales Dashboard**: Track daily/monthly revenue, live users, and active server stats.
- 🔌 **Server Manager**: Add, update, and manage multiple VPN servers from a single dashboard.
- 📦 **Product Management**: Set up custom plans (time/volume limits), pricing, and categories.
- 💳 **Payment Gateways**: Configure crypto (NowPayments) and local gateways with ease.
- ⚙️ **Bot Settings**: Personalize bot messages, buttons, and keyboard menus instantly.

### 📱 Telegram Bot
- 🚀 **Auto-Delivery**: Instantly sends connection configs and QR codes to users after payment.
- 👤 **User Services**: Check subscription info, renew plans, purchase extra volume, and get support.
- 🧪 **Free Trials**: Automated test packages for new users to check connection speed.
- 🔒 **Anti-Abuse**: Mobile phone verification to block spam and multiple trial accounts.
- 🎫 **Ticket Support**: Built-in support system linking users directly to the admin panel.

---

## 🚀 Installation & Update

### Prerequisites
- **OS**: Ubuntu Server 22.04+ (Fresh install recommended)
- **Domain**: A domain pointing to your server IP.

### 🔧 Direct Installation (Stable)
Run this command in your root terminal:
```bash
curl -o install.sh -L https://raw.githubusercontent.com/MasterALiReza/OxBot/main/install.sh && bash install.sh
```
*Select option **1** in the menu.*

### 🔄 Updating
Run the same command and select option **2 (Update OxBot)** to update safely without data loss:
```bash
curl -o install.sh -L https://raw.githubusercontent.com/MasterALiReza/OxBot/main/install.sh && bash install.sh
```

---

## 🙏 Credits & Open Source
This project is built upon the open-source community.
- **Original Source**: OxBot is based on the original **[Mirza Panel by mahdiMGF2](https://github.com/mahdiMGF2/mirzabot)** project. We extend our thanks to the original developers and contributors.
