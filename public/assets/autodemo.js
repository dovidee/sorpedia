// Guided demo (feature 045): a guided run through the whole demo in about five minutes, started from the Demo Hub.
// It runs in the same tab across pages: the step index lives in sessionStorage, and every page that loads this script
// picks it up. Each step shows a caption the presenter can talk over, then acts through the real interface (typing,
// clicking, submitting forms), so what is shown is the actual store and Control Tower. Demo users share the seeded
// demo password. Pause and Stop are always visible; Escape stops.
(() => {
  const KEY = 'sorpedia-autodemo';
  const PASSWORD = 'admin';
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const load = () => { try { return JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch { return null; } };
  const save = (s) => { try { s ? sessionStorage.setItem(KEY, JSON.stringify(s)) : sessionStorage.removeItem(KEY); } catch { /* private window */ } };

  // ---------- Start button on the Demo Hub; the product page to use comes from the server ----------
  const startBtn = document.getElementById('ph-auto');
  startBtn?.addEventListener('click', () => {
    save({ i: 0, t0: Date.now(), paused: 0, product: startBtn.dataset.product });
    try { localStorage.setItem('sorpedia-tour-kontrolltarn', 'done'); } catch { /* private window */ }
    boot();
  });
  if (load()) boot();

  function boot() {
  let S = load();
  const PRODUCT = S.product;
  let paused = false, stopped = false, pausedAt = 0;

  // ---------- Caption bar ----------
  const css = `.autodemo{position:fixed;left:16px;bottom:16px;z-index:2147483000;width:min(440px,calc(100vw - 32px));background:#15211b;color:#f4f1ea;
    border-radius:12px;padding:14px 16px 12px;box-shadow:0 12px 32px rgb(0 0 0/.35);font:14px/1.45 system-ui,sans-serif}
    .autodemo h2{margin:0 0 4px;font-size:16px;line-height:1.3;color:#fff}.autodemo p{margin:0;color:#dcd8cf}
    .autodemo .autodemo-meta{display:flex;align-items:center;gap:8px;margin-top:10px;font-size:12px;color:#b9b4a9}
    .autodemo .autodemo-meta span{margin-right:auto;font-variant-numeric:tabular-nums}
    .autodemo button{min-height:32px;padding:0 12px;border:1px solid #5d6b63;border-radius:8px;background:transparent;color:#fff;font:inherit;cursor:pointer}
    .autodemo button:hover{border-color:#fff}.autodemo button:focus-visible{outline:2px solid #f4c84a;outline-offset:2px}
    .autodemo i{display:block;height:3px;margin-top:10px;border-radius:2px;background:#33423a;overflow:hidden}
    .autodemo i b{display:block;height:100%;width:0;background:#e2c044;transition:width .6s linear}
    .autodemo-hit{outline:3px solid #e2c044!important;outline-offset:3px;border-radius:6px;transition:outline-color .3s}
    @media (prefers-reduced-motion:reduce){.autodemo i b{transition:none}}`;
  const bar = document.createElement('section');
  bar.className = 'autodemo';
  bar.setAttribute('role', 'status');
  bar.setAttribute('aria-live', 'polite');
  bar.setAttribute('aria-label', 'Automatisk gjennomgang');
  bar.innerHTML = `<style>${css}</style><h2></h2><p></p><i><b></b></i>
    <div class="autodemo-meta"><span></span><button type="button" data-j="pause">Pause</button><button type="button" data-j="stop">Stopp</button></div>`;
  const mount = () => document.body.append(bar);
  document.body ? mount() : addEventListener('DOMContentLoaded', mount);
  const h2 = bar.querySelector('h2'), para = bar.querySelector('p'), prog = bar.querySelector('i b'), meta = bar.querySelector('.autodemo-meta span');
  const pauseBtn = bar.querySelector('[data-j=pause]');
  const stop = () => { stopped = true; save(null); bar.remove(); };
  bar.addEventListener('click', (e) => {
    const b = e.target.closest('[data-j]');
    if (!b) return;
    if (b.dataset.j === 'stop') return stop();
    paused = !paused;
    if (paused) pausedAt = Date.now(); else { S.paused += Date.now() - pausedAt; save(S); }
    pauseBtn.textContent = paused ? 'Fortsett' : 'Pause';
  });
  addEventListener('keydown', (e) => { if (e.key === 'Escape') stop(); });
  const clock = () => {
    const s = Math.max(0, Math.round((Date.now() - S.t0 - S.paused - (paused ? Date.now() - pausedAt : 0)) / 1000));
    meta.textContent = `Steg ${Math.min(Math.max(S.i, 1), STEPS.length)} av ${STEPS.length}, ${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')} av 5:00`;
  };
  setInterval(clock, 1000);

  // ---------- Helpers ----------
  class Stop extends Error {}
  const sleep = async (ms) => {
    const end = Date.now() + ms;
    for (let left = ms; left > 0; left = end - Date.now()) {
      if (stopped) throw new Stop();
      await new Promise((r) => setTimeout(r, Math.min(left, 200)));
      while (paused && !stopped) await new Promise((r) => setTimeout(r, 200));
    }
    if (stopped) throw new Stop();
  };
  const find = async (sel, ms = 10000) => {
    for (const end = Date.now() + ms; Date.now() < end; await sleep(200)) {
      const el = typeof sel === 'function' ? sel() : document.querySelector(sel);
      if (el && el.getClientRects().length) return el;
    }
    return null;
  };
  const point = async (el, ms = 900) => {
    if (!el) return;
    el.scrollIntoView({ block: 'center', behavior: reduce ? 'auto' : 'smooth' });
    el.classList.add('autodemo-hit');
    await sleep(ms);
    el.classList.remove('autodemo-hit');
  };
  const click = async (sel, ms) => { const el = await find(sel); if (el) { await point(el, ms); el.click(); } return el; };
  const type = async (sel, text) => {
    const el = await find(sel);
    if (!el) return null;
    await point(el, 400);
    el.focus();
    el.value = '';
    for (const ch of text) { el.value += ch; el.dispatchEvent(new Event('input', { bubbles: true })); await sleep(reduce ? 0 : 38); }
    return el;
  };
  const scroll = async (y, ms) => { scrollBy({ top: y, behavior: reduce ? 'auto' : 'smooth' }); await sleep(ms); };
  const say = (title, text) => { h2.textContent = title; para.textContent = text; prog.style.width = `${Math.round(((S.i + 1) / STEPS.length) * 100)}%`; clock(); };
  const go = (url) => { location.assign(url); return new Promise(() => {}); };
  // A click that submits a form or follows a link: wait for the new page, so a pending POST is never cut off
  const leave = async (sel, ms) => { if (await click(sel, ms)) return new Promise(() => {}); };
  const csrf = async () => document.querySelector('meta[name=csrf]')?.content
    || (await (await fetch('/logg-inn')).text()).match(/name="csrf" value="([^"]+)"/)?.[1] || '';
  const logout = async () => {
    const body = new URLSearchParams({ csrf: await csrf() });
    await fetch('/logg-ut', { method: 'POST', body, redirect: 'manual' }).catch(() => null);
  };
  const login = async (email, back) => {
    await type('input[name=email]', email);
    await type('input[name=password]', PASSWORD);
    const form = document.querySelector('form[action="/logg-inn"]');
    const b = form?.querySelector('input[name=back]');
    if (b) b.value = back;
    await sleep(500);
    form?.requestSubmit();
    return new Promise(() => {});
  };
  // One message to Søren in the shop chat, waiting for the answer to finish, then time to read it
  const chat = async (text, read = 6000) => {
    if (!document.getElementById('soren-m')) await click('#soren-open', 400);
    const input = await type('#soren-m', text);
    if (!input) return;
    await sleep(300);
    input.form.requestSubmit();
    await sleep(1200);
    const log = document.querySelector('[aria-busy]');
    for (const end = Date.now() + 25000; log?.getAttribute('aria-busy') === 'true' && Date.now() < end;) await sleep(300);
    await sleep(read);
  };
  const closeChat = () => document.querySelector('.chat-btns .x')?.click();
  const view = (v) => click(`input[name=view][value=${v}]`, 500);

  // ---------- The tour: about five minutes in all ----------
  const STEPS = [
    { title: 'Automatisk gjennomgang', text: 'Vi går gjennom hele løsningen: kunden handler og får hjelp av Søren, og så tar en ansatt over i kontrolltårnet.',
      run: async () => { await sleep(4000); await logout(); await go('/logg-inn?back=/'); } },
    { url: '/logg-inn', title: 'Innlogging som kunde', text: 'En verifisert kunde logger inn. Passord lagres med Argon2id, og kontoen låses etter fem feil.',
      run: () => login('kunde@sorpedia.test', '/') },
    { url: '/', title: 'Forsiden', text: 'Temaer, butikkvelger med lager per butikk og Søren nederst til høyre. Alt innhold er Sør-Tres egne varer.',
      run: async () => { await sleep(4000); await scroll(500, 4000); await scroll(500, 4000); await scroll(-1000, 2000); } },
    { url: '/sok?q=hodelykt', title: 'Søk og filtre', text: 'Søket forstår bøyninger og synonymer. Filtrene bygges fra spesifikasjonene som er registrert på varene.',
      run: async () => { await sleep(4000); await scroll(400, 4000); await scroll(-400, 3000); } },
    { url: PRODUCT, title: 'Produktsiden', text: 'Hver spesifikasjon har kilde og nivå: oppgitt av Sør-Tre, utledet, fra kjøper eller godkjent av ansatt. «Passer sammen med» viser hvor sikkert koblingen er.',
      run: async () => { await sleep(4000); await scroll(500, 5000); await scroll(500, 5000); await scroll(-1000, 2000); } },
    { url: PRODUCT, title: 'Søren svarer fra katalogen', text: 'Pris, lager og spesifikasjoner kommer alltid fra databasen. Den lokale modellen leser bare spørsmålet.',
      run: async () => { await chat('Er den vanntett?', 5000); await chat('Anbefal en hodelykt til fjelltur under 1500 kr', 7000); } },
    { url: PRODUCT, title: 'Oppfølging og samtale', text: 'Søren husker forslagene i samtalen og sammenligner dem. Småprat får et kort svar uten produktsøk.',
      run: async () => { await chat('Hva er forskjellen på dem?', 8000); await chat('1 + 1', 3500); } },
    { url: PRODUCT, title: 'Kjøp', text: 'Kunden legger hodelykten i handlekurven.',
      run: async () => { closeChat(); await sleep(800); await leave('form.add-row button', 1200); } },
    { url: '/handlekurv', title: 'Handlekurven', text: 'Handlekurven foreslår tilbehør som passer, med samme nivå for hvor sikker koblingen er.',
      run: async () => { await sleep(5000); await leave('a[href="/kasse"]', 1200); } },
    { url: '/kasse', title: 'Kassen', text: 'Betaling er simulert i demoen. Kjøpet gjør kunden til verifisert kjøper som kan skrive anmeldelser.',
      run: async () => { await sleep(5000); await leave('form.pay button', 1500); } },
    { on: /^\/ordre\/\d+$/, title: 'Ordrebekreftelse', text: 'Ordren viser angrefrist og betalingsmåte. Lageret er trukket i butikken.',
      run: async () => { S.order = +(location.pathname.match(/\d+/)?.[0] ?? 0); save(S); await sleep(6000); await go('/byggelab'); } },
    { url: '/byggelab', on: /^\/byggelab/, title: 'Byggelab', text: 'Kunden setter sammen et oppsett. Testkjøringen sjekker at delene passer sammen, ut fra registrerte verdier.',
      run: async () => { await sleep(3000); await click('[data-preset]', 1000); await sleep(5000); await click('[data-act=run]', 1000); await sleep(9000); } },
    { url: PRODUCT, title: 'Retur via Søren', text: 'Kunden vil returnere varen og ber om et menneske. Søren kobler til kundeservice og saken opprettes med samtalen.',
      run: async () => {
        await chat('Kan jeg snakke med et menneske?', 1500);
        await click('[data-yes]', 1000);
        const sys = await find(() => [...document.querySelectorAll('.msg')].find((m) => /#\d+/.test(m.textContent) && /Sak/.test(m.textContent)), 10000);
        S.ticket = +(sys?.textContent.match(/#(\d+)/)?.[1] ?? 0);
        save(S);
        await sleep(2000);
        const input = await type('#soren-m', 'Jeg vil returnere hodelykten jeg nettopp kjøpte og få pengene tilbake.');
        input?.form.requestSubmit();
        await sleep(4000);
        closeChat();
        await logout();
        await go('/logg-inn?back=/ansatt/tarn');
      } },
    { url: '/logg-inn', title: 'Bytte til ansatt', text: 'Nå logger en ansatt inn. Kontrolltårnet er bare for ansatte, og hver rute sjekker rollen på serveren.',
      run: () => login('ansatt@sorpedia.test', '/ansatt/tarn') },
    { url: '/ansatt/tarn', title: 'Kontrolltårnet: oversikt', text: 'Det viktigste først: saker som venter, varer som mangler og leveranser på vei.',
      run: async () => { await sleep(14000); } },
    { url: '/ansatt/tarn', title: 'Kundeservice og refusjon', text: 'Saken fra Søren ligger her med hele samtalen. Den ansatte refunderer ordren, og kunden får melding.',
      run: async () => {
        await view('kundeservice');
        await sleep(2500);
        await click(S.ticket ? `[data-ticket="${S.ticket}"]` : '[data-ticket]', 1200);
        await sleep(4000);
        await click(S.order ? `[data-refund=all][data-o="${S.order}"]` : '[data-refund=all]', 1200);
        await sleep(2500);
        await click('[data-do]', 1200);
        await sleep(5000);
      } },
    { url: '/ansatt/tarn', title: 'Innsikt: mangler i butikken', text: 'Bestselgere som er tomme i butikken, med lager i andre butikker. Én linje legges på bestillingslisten.',
      run: async () => {
        await view('innsikt');
        await sleep(4000);
        await click('#tw-ins [data-want]', 1500);
        await sleep(4000);
        await click('#ins-tab-trend', 800);
        await sleep(8000);
      } },
    { url: '/ansatt/tarn', title: 'Transport', text: 'Bestillingen hentes av en ledig bil innen 60 km. Kartet viser vær fra MET, veimeldinger fra Statens vegvesen og ferger fra Entur, hver med kilde.',
      run: async () => {
        await view('transport');
        await sleep(4000);
        if (document.querySelector('#tw-sim [data-sim=play]')) await click('#tw-sim [data-sim=play]', 800);
        if (await click('#tw-sim [data-batch]', 1000)) { await sleep(2000); await click('#tw-inspect [data-order-batch]:not([disabled])', 1200); }
        await sleep(2000);
        const rates = [...document.querySelectorAll('#tw-sim [data-rate]')];
        if (rates.length) await click(() => rates[rates.length - 1], 800);
        await sleep(12000);
        await click('#tw-sim [data-scen=storm]', 1000);
        await sleep(9000);
        await click('#tw-sim [data-scen=ekte]', 800);
        await sleep(8000);
      } },
    { url: '/ansatt/tarn', title: 'Gulv', text: 'Varemottaket: hver vare går gjennom stasjonene fra mottak til klar, og avvik blir oppgaver.',
      run: async () => { await view('gulv'); await sleep(14000); } },
    { title: 'Takk', text: 'Det var hele runden. Alt vi viste er laget med ekte varer fra Sør-Tre og merket demo der dataene er simulert.',
      run: async () => { await sleep(1000); await go('/presentasjon#oversikt'); } },
  ];

  // ---------- Runner ----------
  // Where a step runs: its own pattern, else its address. A step with a pattern and no address is reached by a form only.
  const at = (st) => (st.on ? st.on.test(location.pathname) : st.url.includes('?') ? location.pathname + location.search === st.url : location.pathname === st.url);
  async function run() {
    S = load();
    if (!S) return;
    try {
      while (S.i < STEPS.length) {
        const step = STEPS[S.i];
        if ((step.url || step.on) && !at(step)) {
          if (!step.url) { S.i++; save(S); continue; }
          return go(step.url);
        }
        say(step.title, step.text);
        const me = S.i;
        S.i = me + 1; // saved before acting, so a step that leaves the page resumes at the next one
        save(S);
        await step.run();
        if (S.i === STEPS.length) break;
      }
      // The last step returns to the hub
      stop();
    } catch (e) {
      if (!(e instanceof Stop)) { say('Gjennomgangen stoppet', 'Et steg feilet. Trykk Stopp, og start igjen fra Demo Hub.'); console.error(e); }
    }
  }
  document.readyState === 'loading' ? addEventListener('DOMContentLoaded', () => setTimeout(run, 900)) : setTimeout(run, 900);
  }
})();
