<!DOCTYPE html>
<html lang="uz">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POSTIX AI — Foydalanuvchi Qo‘llanmasi</title>
  <meta name="color-scheme" content="light dark">
  <style>
    :root {
      --bg: #ffffff;
      --text: #111827;
      --muted: #374151;
      --border: #e5e7eb;
      --card: #f9fafb;
      --accent: #2563eb;
      --header-height: 64px;
    }
    [data-theme="dark"] {
      --bg: #0b1220;
      --text: #e5e7eb;
      --muted: #9ca3af;
      --border: #1f2937;
      --card: #111827;
      --accent: #60a5fa;
    }
    @media (prefers-color-scheme: dark) {
      :root:not([data-theme]) {
        --bg: #0b1220;
        --text: #e5e7eb;
        --muted: #9ca3af;
        --border: #1f2937;
        --card: #111827;
        --accent: #60a5fa;
      }
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Ubuntu, "Helvetica Neue";
      background: var(--bg);
      color: var(--text);
      line-height: 1.7;
      padding-top: var(--header-height);
    }
    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }
    .topbar {
      height: var(--header-height);
      position: fixed;
      top: 0; left: 0; right: 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 18px;
      gap: 12px;
      background: linear-gradient(180deg, rgba(0,0,0,0.02), transparent);
      border-bottom: 1px solid var(--border);
      backdrop-filter: blur(6px);
      z-index: 60;
    }
    .brand { display: flex; gap: 12px; align-items: center; font-weight: 600; }
    .brand .dot { width: 10px; height: 10px; background: var(--accent); border-radius: 50%; }
    .actions { display:flex; gap:8px; align-items:center; }
    .btn { background: var(--card); border: 1px solid var(--border); padding:8px 10px; border-radius:10px; cursor:pointer; display:inline-flex; gap:8px; align-items:center; }
    .btn.small { padding:6px 8px; font-size:14px; }
    .container { max-width: 1100px; margin: 20px auto 80px; padding: 20px; display: grid; grid-template-columns: 250px 1fr; gap: 24px; }
    @media (max-width: 880px) { .container { grid-template-columns: 1fr; padding: 12px; } .toc { order: 2; } }
    h1, h2, h3 { line-height: 1.3; }
    h1 { font-size: 28px; margin-bottom: 6px; }
    .toc {
      position: sticky; top: calc(var(--header-height) + 12px);
      align-self: start;
      background: var(--card);
      border: 1px solid var(--border);
      padding: 12px;
      border-radius: 10px;
      max-height: calc(100vh - var(--header-height) - 36px);
      overflow: auto;
    }
    .toc h3 { margin: 0 0 8px; }
    .toc ul { padding-left: 12px; margin:0; list-style: none; }
    .toc li { margin:6px 0; }
    .toc a { color: var(--muted); font-size: 14px; display:block; padding:6px 8px; border-radius:6px; }
    .toc a.active { background: linear-gradient(90deg, rgba(37,99,235,0.08), transparent); color: var(--accent); }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 18px; margin-top: 16px; }
    .muted { color: var(--muted); }
    code { background: rgba(0,0,0,.06); padding: 3px 6px; border-radius: 6px; }
    footer { position: fixed; left: 0; right: 0; bottom: 0; height: 56px; display:flex; align-items:center; justify-content:center; gap:12px; padding: 8px 16px; background: linear-gradient(0deg, rgba(0,0,0,0.02), transparent); border-top: 1px solid var(--border); z-index:50; }
    .small-muted { font-size:13px; color:var(--muted); }
    .content { padding-bottom: 40px; }
    .section-anchor { scroll-margin-top: calc(var(--header-height) + 10px); }
    pre.plain { white-space: pre-wrap; font-family: inherit; background: var(--card); padding: 12px; border-radius: 8px; border:1px solid var(--border); overflow:auto; }
  </style>
</head>
<body>

  <header class="topbar">
    <div class="brand">
      <span class="dot"></span>
      <div>
        <div style="font-weight:700">POSTIX AI</div>
        <div style="font-size:12px; margin-top:-2px; color:var(--muted)">Foydalanuvchi Qo‘llanmasi</div>
      </div>
    </div>

    <div class="actions">
      <div class="top-actions">
        <a class="btn small" href="https://t.me/PostixAI_bot" target="_blank" rel="noopener" aria-label="Open PostixAI bot">🔗 Botga o‘tish</a>
        <button id="theme-toggle" class="btn small" title="Toggle theme">🌙</button>
      </div>
    </div>
  </header>

  <main class="container">

    <nav class="toc" aria-label="Mundarija">
      <h3>📑 Mundarija</h3>
      <ul id="toc-list"></ul>
      <div style="margin-top:10px; font-size:13px; color:var(--muted)">Sahifada tepadan pastga harakat qiling va sarlavhalarni tanlang.</div>
    </nav>

    <article class="content" id="main-content">
      <div class="card">
        <h1>🗂 POSTIX AI — Foydalanuvchi Qo‘llanmasi (pro)</h1>

        <!-- SECTION: Full verbatim text is split into blocks. Each block contains an identical visible heading (h2/h3) AND the exact original text in a pre block. This ensures:
             - every character remains unchanged in the pre blocks
             - TOC links work via the visible headings
         -->

        <h2 id="intro" class="section-anchor">Kirish</h2>
        <pre class="plain">🗂 POSTIX AI — Foydalanuvchi qo‘llanmasi (pro)
________________________________________
Kirish
Postix AI — Telegram orqali xabarlarni oldindan rejalashtirish va yuborish imkonini beruvchi tizim (ilova / bot). Ushbu hujjat Client (mijoz tashkiloti) va Admin (Postix AI — xizmat ko‘rsatuvchi) tomonlari uchun tizimdan qanday foydalanishni bosqichma-bosqich tushuntiradi.
Tizimda kim kim?
•	Admin — Postix AI jamoasi; xizmatni o‘rnatadi, hisoblarni yaratadi va texnik qo‘llab-quvvatni taqdim etadi.
•	Client — xizmatdan foydalanuvchi tashkilot. Client ichida hisob yaratish va ichki shaxslarni (foydalanuvchilar) belgilash mumkin.
•	Client foydalanuvchilari — Client tomonidan tizimga qo‘shilgan shaxslar; ular bot orqali xabar yuboradi yoki monitoring qiladi.
________________________________________</pre>

        <h2 id="1" class="section-anchor">1. 📌 Umumiy ma'lumot</h2>
        <pre class="plain">1. 📌 Umumiy ma'lumot
Ushbu hujjat Client va uning ichki foydalanuvchilari uchun mo‘ljallangan. Hujjatdagi amallar va yo‘riqnomalar, aniq va tartibli taqdim etilgan.
________________________________________</pre>

        <h2 id="2" class="section-anchor">2. 🗂 Ro‘yxatdan o‘tish va talab qilinadigan ma’lumotlar</h2>
        <pre class="plain">2. 🗂 Ro‘yxatdan o‘tish va talab qilinadigan ma’lumotlar
Kim nima beradi va kim nima qiladi?
•	Client taqdim etadi: Client tizimga ishlatishi kerak bo‘lgan ma’lumotlarni Adminga yuboradi.
o	Talab qilinadigan ma’lumotlar:
	Bo‘lim nomi (agar bo‘limmi asosida hisob yaratilsa)
	Telegram ID (majburiy)
	Email
	Parol (Client tarafdan belgilanadi — biz hisobni yaratamiz)
•	Admin qiladi: Admin Client uchun hisob yaratib, kirish ma’lumotlarini (login) taqdim etadi va texnik qo‘llab-quvvatni amalga oshiradi.
Eslatma: Client bizga kerakli ma’lumotlarni yuboradi; Admin hisob yaratilgach kirish ma’lumotlarini Clientga beradi.
________________________________________</pre>

        <h2 id="3" class="section-anchor">3. 🔐 Admin(client) panelga kirish</h2>
        <pre class="plain">3. 🔐 Admin(client) panelga kirish 
🔹 Kirish manzili
•	URL: https://postixai.websol.uz/login
🔹 Kirish tartibi
•	Tizimga kirish — email va parol orqali amalga oshiriladi (kirish ma’lumotlari Admin tomonidan taqdim etiladi).
🔸 Rollar va ularning vazifalari (soddalashtirilgan)
•	Admin (Postix AI)
o	Tizimni sozlash, Client hisoblarini yaratish va umumiy texnik qo‘llab-quvvatni taqdim etadi.
•	Client (tashkilot)
o	O‘z tashkiloti doirasida foydalanuvchilarni belgilaydi va boshqaradi. Client ichida bir yoki bir nechta mas’ullar (bo‘lim mas’ullari) belgilanishi mumkin.
•	Client foydalanuvchilari
o	Tizimga qo‘shilgan shaxslar; bot orqali xabarlarni yuboradi va o‘ziga tegishli monitoringni ko‘radi.
🔸 Foydalanuvchi qo‘shish limiti
•	Biz tomonda har bir Client uchun foydalanuvchi qo‘shish limiti o‘rnatiladi. Bu limit Client tomonidan oshirib bo‘lmaydi.
•	Agar limit to‘lgan bo‘lsa, yangi foydalanuvchi qo‘shish uchun Client quyidagilardan birini amalga oshirishi lozim:
1.	Mavjud foydalanuvchilardan birini o‘chirish; yoki
2.	Adminga murojaat qilib qo‘shimcha foydalanuvchi qo‘yish bo‘yicha kelishuv o‘tkazish.
________________________________________</pre>

        <h2 id="4" class="section-anchor">4. ➕ Yangi foydalanuvchi qo‘shish</h2>
        <pre class="plain">4. ➕ Yangi foydalanuvchi qo‘shish
🔹 Sahifa
•	URL: https://postixai.websol.uz/admin/new-telegram-users
🔸 Qo‘shish tartibi
1.	Tizimda ro‘yxatdan o‘tmagan telefon raqamni kiriting.
2.	Telegram yuborgan 5 xonali kodni kiriting.
3.	Agar barcha ma’lumotlar to‘g‘ri bo‘lsa, foydalanuvchi Clientning belgilangan bo‘limiga qo‘shiladi.
🔸 Qo‘shimcha imkoniyatlar
•	Bir foydalanuvchiga qo‘shimcha Telegram raqamlar qo‘shish mumkin.
•	Foydalanuvchini bloklash va blokdan chiqarish imkoniyati mavjud (botdan foydalanishni cheklash yoki tiklash).
Eslatma: Telegram ichida tasdiq kodini ochiq joylarda ulashish kodning yaroqsizligini keltirib chiqarishi mumkin.
________________________________________</pre>

        <h2 id="5" class="section-anchor">5. 🤖 Botga kirish va foydalanish </h2>
        <pre class="plain">5. 🤖 Botga kirish va foydalanish 
🔹 Bot manzili
•	URL: https://t.me/PostixAI_bot
🔸 Boshlash
•	Bot bilan ishlash uchun botga kirib /start buyrug‘ini yuboring.
🔸 Bot yordamida ishlash uchun talablar
•	Telefon raqam Telegramga ulangan va faol ses¬siyaga ega bo‘lishi kerak (logout/terminate qilingan bo‘lmasligi lozim).
•	Xabar yuborish uchun tegishli catalog (katalog) mavjud bo‘lishi kerak.
🔸 Tarix va monitoring
•	Bot orqali yuborilgan xabarlar tarixini ko‘rish uchun /history buyrug‘i mavjud — u orqali yuborilgan xabarlar tarixini ko‘rishingiz mumkin.
________________________________________</pre>

        <h2 id="6" class="section-anchor">6. 📚 Catalog (Katalog)</h2>
        <pre class="plain">6. 📚 Catalog (Katalog)
🔹 Catalog nima?
Catalog — xabar yuboriladigan Telegram chatlari yoki peerlar (individual foydalanuvchi, guruh va hokazo) to‘plami. Har bir catalogga nom berib, kerakli chatlarni qo‘shish mumkin.
🔸 Catalog yaratish va boshqarish
1.	Catalogga nom qo‘ying.
2.	Kerakli chat yoki peerlarni qo‘shing.
3.	Catalog tanlangan holda, shu catalog orqali xabar yuborish mumkin.
________________________________________</pre>

        <h2 id="7" class="section-anchor">7. ✉️ Xabar yuborish tartibi</h2>
        <pre class="plain">7. ✉️ Xabar yuborish tartibi
🔹 Bosqichlar
1.	Xabar yuborish tugmasini bosing.
2.	Kerakli catalogni tanlang.
3.	Telefon raqamni tanlang.
4.	Yuborilishi kerak bo‘lgan matnni kiriting.
5.	Necha marta va qaysi vaqt oralig‘ida yuborilishini belgilang.
🔸 Parametrlar misoli
•	Bir xabar belgilangan vaqt oralig‘ida ketma-ket peerlar bo‘yicha yuboriladi.
•	Yuborish jarayoni avtomatik rejalashtiriladi va tizim tomonidan nazorat qilinadi.
________________________________________</pre>

        <h2 id="8" class="section-anchor">8. 📊 Xabarlarni monitoring va nazorat qilish</h2>
        <pre class="plain">8. 📊 Xabarlarni monitoring va nazorat qilish
Postix AI har bir yuborilgan yoki rejalashtirilgan xabar bo‘yicha to‘liq monitoring imkoniyatini taqdim etadi.
🔹 Monitoring nimani ko‘rsatadi?
•	Guruh (xabar paketi) ID raqami
•	Xabar yuborilishi boshlangan va tugash vaqti
•	Oxirgi yuborilgan vaqt (Last sent at)
•	Yuborilgan xabarning to‘liq matni
•	Har bir peer (chat/guruh) bo‘yicha yuborilish holati
🔸 Foydalanuvchi huquqlari
•	Client foydalanuvchisi faqat o‘zi yuborgan xabarlar bo‘yicha monitoringni ko‘ra oladi.
•	Boshqa Client foydalanuvchilarining xabarlariga kirish imkoni yo‘q (faqat Admin yoki Client mas’ullari ruxsati bilan).
🔸 Boshqaruv imkoniyatlari
•	Xabarlarni bekor qilish (cancel)
•	Hali yuborilmagan xabarlarni schedule ro‘yxatidan olib tashlash
•	Turli sabablarga ko‘ra yuborilmagan xabarlar avtomatik belgilanadi
Eslatma: Cancel qilingan xabarlar Telegram’ga yuborilmaydi va monitoringda bekor qilingan holatda ko‘rsatiladi.
________________________________________</pre>

        <h2 id="9" class="section-anchor">9. ✅ Amaliy tavsiyalar va eslatmalar</h2>
        <pre class="plain">9. ✅ Amaliy tavsiyalar va eslatmalar
•	Foydalanuvchi sessiyalari faol bo‘lishi kerak (agar sessiya logout qilingan bo‘lsa, xabar yuborish ishlamaydi).
•	Bo‘lim mas’uliyatlarini aniq belgilash orqali tizim xavfsizligini ta’minlang.
•	Xizmat bilan bog‘liq savollar va yangi foydalanuvchi qo‘shish talablariga quyidagi yo‘l bilan murojaat qiling: Admin texnik qo‘llab-quvvatiga.
________________________________________</pre>

        <h2 id="10" class="section-anchor">10. ❓ Tez-tez so‘raladigan savollar (FAQ)</h2>
        <pre class="plain">10. ❓ Tez-tez so‘raladigan savollar (FAQ)
Q: Telegram IDni qanday topish mumkin?
A: Telegramda @username_to_id_bot yoki shunga o‘xshash botlardan foydalanish orqali IDni aniqlash mumkin. (Admin yordamida ham ID olinishi mumkin.)
Q: Kod yetib kelmasa nima qilish kerak?
A: Telefon signalini va Telegram sessiyasini tekshiring. Agar kod kelmasa, Adminga murojaat qiling.
Q: Foydalanuvchi bloklanganini qanday tiklash mumkin?
A: Admin yoki Client mas’uli orqali foydalanuvchini tanlab "Unblock" funksiyasini ishlating.
________________________________________</pre>

        <h2 id="11" class="section-anchor">11. 🔒 Qo‘shimcha xavfsizlik tavsiyalari</h2>
        <pre class="plain">11. 🔒 Qo‘shimcha xavfsizlik tavsiyalari
•	Kodlar va parollarni faqat ishonchli kanallarda saqlang.
•	Xabar yuborish limitlari va spam himoyasi (Flood Wait yoki o‘xshash sozlamalar) ni ko‘rib chiqing.</pre>

      </div>

    </article>

  </main>

  <footer>
    <div class="small-muted">© POSTIX AI — User Guide</div>
    <div style="width:1px; height:18px; background:var(--border); margin:0 10px"></div>
    <a href="#main-content" class="small-muted">Back to top</a>
  </footer>

  <script>
    // Auto-generate TOC from h2 elements (visible headings)
    document.addEventListener('DOMContentLoaded', function () {
      const tocList = document.getElementById('toc-list');
      const headings = Array.from(document.querySelectorAll('h2.section-anchor'));
      if (!tocList || headings.length === 0) return;

      headings.forEach((h, idx) => {
        if (!h.id) h.id = 'section-' + idx;
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = '#' + h.id;
        a.textContent = h.textContent.trim();
        a.addEventListener('click', function () {
          setTimeout(() => { window.scrollBy(0, -10); }, 0);
        });
        li.appendChild(a);
        tocList.appendChild(li);
      });

      const tocLinks = Array.from(tocList.querySelectorAll('a'));
      function onScroll() {
        let activeIndex = 0;
        for (let i = 0; i < headings.length; i++) {
          const rect = headings[i].getBoundingClientRect();
          if (rect.top - (parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-height')) || 64) <= 10) {
            activeIndex = i;
          }
        }
        tocLinks.forEach((link, i) => link.classList.toggle('active', i === activeIndex));
      }
      window.addEventListener('scroll', onScroll);
      onScroll();

      // Theme toggle
      const themeToggle = document.getElementById('theme-toggle');
      function applyTheme(theme) {
        if (theme) document.documentElement.setAttribute('data-theme', theme);
        else document.documentElement.removeAttribute('data-theme');
      }
      const saved = localStorage.getItem('postix-theme');
      if (saved) applyTheme(saved);
      function updateToggleIcon() {
        const current = document.documentElement.getAttribute('data-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        themeToggle.textContent = current === 'dark' ? '🌙' : '☀️';
      }
      updateToggleIcon();
      themeToggle.addEventListener('click', function () {
        const current = document.documentElement.getAttribute('data-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        const next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        localStorage.setItem('postix-theme', next);
        updateToggleIcon();
      });

      tocList.querySelectorAll('a').forEach(a => a.setAttribute('tabindex','0'));
    });
  </script>

</body>
</html>
