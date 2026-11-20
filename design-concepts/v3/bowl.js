// design-concepts/v3/bowl.js — 麻辣湯のどんぶりを SVG で描く (写真のかわり). Owner: Lin
// bowlSVG({ seed, rim, broth, steam }) → <svg> の文字列
//   seed  : 同じ数なら同じ盛りつけ (メニューごとに少しずつちがう絵になる)
//   rim   : どんぶりの色 / broth : 'mala' (赤) | 'tomato' | 'paitan' (白) | 'mushroom'
//   steam : 湯気を出すか

const BROTHS = {
  mala:     ['#F47A3C', '#D7351F', '#9E1D12'],
  tomato:   ['#FF8A5B', '#E2452B', '#B22A1B'],
  paitan:   ['#FFF3DD', '#F2D9AE', '#D9B57C'],
  mushroom: ['#D9A066', '#A8642F', '#6E3B1A'],
};

function bowlRandom(seed) {
  let a = (seed * 2654435761) >>> 0;
  return () => {
    a = (a + 0x6D2B79F5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function bowlSVG(opts = {}) {
  const seed = opts.seed ?? 1;
  const rim = opts.rim ?? '#F5EFE6';
  const rimShade = opts.rimShade ?? 'rgba(0,0,0,.12)';
  const [b1, b2, b3] = BROTHS[opts.broth || 'mala'];
  const r = bowlRandom(seed);
  const id = 'b' + seed + Math.floor(r() * 1e6);
  const at = (rad, ang) => [100 + rad * Math.cos(ang), 100 + rad * Math.sin(ang)];
  let food = '';

  // 春雨 (noodles): 波線
  for (let i = 0; i < 7; i++) {
    const y = 70 + i * 9 + r() * 4, x = 52 + r() * 10;
    food += `<path d="M${x} ${y} q12 -7 24 0 t24 0 t24 0 t20 0" fill="none" stroke="#FBE8C0" stroke-width="3.2" stroke-linecap="round" opacity=".92"/>`;
  }
  // チンゲン菜 (bok choy)
  for (let i = 0; i < 3; i++) {
    const [x, y] = at(46 + r() * 16, r() * Math.PI * 2);
    const rot = Math.floor(r() * 180);
    food += `<g transform="translate(${x} ${y}) rotate(${rot})"><ellipse rx="17" ry="9" fill="#5E9E3A"/><ellipse rx="15" ry="6" fill="#79B94E"/><path d="M-15 0 H15" stroke="#C9E39A" stroke-width="2.4" stroke-linecap="round"/><ellipse cx="11" rx="7" ry="5" fill="#E4F2C4"/></g>`;
  }
  // 豆腐 (tofu)
  for (let i = 0; i < 3; i++) {
    const [x, y] = at(30 + r() * 30, r() * Math.PI * 2);
    const rot = Math.floor(r() * 90);
    food += `<rect x="${x - 9}" y="${y - 9}" width="18" height="18" rx="4" fill="#FFF6E2" stroke="#EBD6AE" stroke-width="1.2" transform="rotate(${rot} ${x} ${y})"/>`;
  }
  // 牛肉 (beef slices)
  for (let i = 0; i < 2; i++) {
    const [x, y] = at(28 + r() * 34, r() * Math.PI * 2);
    const rot = Math.floor(r() * 180);
    food += `<g transform="translate(${x} ${y}) rotate(${rot})"><path d="M-14 -5 C-6 -12 8 -11 15 -3 C9 7 -6 9 -14 3 Z" fill="#8E3A2A"/><path d="M-9 -2 C-3 -6 6 -5 10 -1" stroke="#D99A84" stroke-width="1.6" fill="none" stroke-linecap="round"/></g>`;
  }
  // えのき (enoki)
  for (let i = 0; i < 2; i++) {
    const [x, y] = at(40 + r() * 22, r() * Math.PI * 2);
    const rot = Math.floor(r() * 180);
    let g = '';
    for (let k = -2; k <= 2; k++) g += `<path d="M${k * 2.4} 10 L${k * 3.2} -8" stroke="#FFF8E8" stroke-width="1.6" stroke-linecap="round"/><circle cx="${k * 3.2}" cy="-9" r="1.9" fill="#F7EBD0"/>`;
    food += `<g transform="translate(${x} ${y}) rotate(${rot})">${g}</g>`;
  }
  // 唐辛子の輪切り (chili rings)
  for (let i = 0; i < 6; i++) {
    const [x, y] = at(12 + r() * 62, r() * Math.PI * 2);
    food += `<circle cx="${x}" cy="${y}" r="4.6" fill="none" stroke="#C3170F" stroke-width="2.6"/><circle cx="${x + 1}" cy="${y - .5}" r="1" fill="#F7D36A"/>`;
  }
  // 油の玉とごま (oil drops, sesame)
  for (let i = 0; i < 12; i++) {
    const [x, y] = at(r() * 74, r() * Math.PI * 2);
    food += `<circle cx="${x}" cy="${y}" r="${1.2 + r() * 2.4}" fill="#FFB347" opacity=".55"/>`;
  }
  for (let i = 0; i < 14; i++) {
    const [x, y] = at(r() * 70, r() * Math.PI * 2);
    food += `<ellipse cx="${x}" cy="${y}" rx="1.6" ry=".9" fill="#FFF4D6" transform="rotate(${Math.floor(r() * 180)} ${x} ${y})"/>`;
  }
  // パクチー (cilantro)
  for (let i = 0; i < 4; i++) {
    const [x, y] = at(r() * 40, r() * Math.PI * 2);
    food += `<path d="M${x} ${y} l3 -3 l3 3 l-3 3 z" fill="#3E8B2F"/>`;
  }

  const steam = opts.steam
    ? `<g class="bowl-steam" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".55">
         <path d="M78 22 c-8 -10 8 -16 0 -26"/><path d="M100 18 c-8 -10 8 -16 0 -26"/><path d="M122 22 c-8 -10 8 -16 0 -26"/>
       </g>` : '';

  return `
  <svg class="bowl" viewBox="0 -30 200 230" role="img" aria-label="麻辣湯のどんぶり">
    <defs>
      <radialGradient id="${id}br" cx="45%" cy="40%" r="60%">
        <stop offset="0" stop-color="${b1}"/><stop offset=".65" stop-color="${b2}"/><stop offset="1" stop-color="${b3}"/>
      </radialGradient>
      <radialGradient id="${id}rim" cx="50%" cy="45%" r="55%">
        <stop offset=".85" stop-color="${rim}"/><stop offset="1" stop-color="${rim}"/>
      </radialGradient>
      <clipPath id="${id}clip"><circle cx="100" cy="100" r="80"/></clipPath>
    </defs>
    <ellipse cx="100" cy="196" rx="74" ry="8" fill="rgba(0,0,0,.18)"/>
    <circle cx="100" cy="100" r="96" fill="url(#${id}rim)"/>
    <circle cx="100" cy="100" r="96" fill="none" stroke="${rimShade}" stroke-width="2"/>
    <circle cx="100" cy="100" r="82" fill="${rimShade}"/>
    <circle cx="100" cy="100" r="80" fill="url(#${id}br)"/>
    <g clip-path="url(#${id}clip)">${food}</g>
    <ellipse cx="72" cy="62" rx="26" ry="12" fill="#fff" opacity=".10" transform="rotate(-25 72 62)"/>
    ${steam}
  </svg>`;
}

// 唐辛子のアイコン (辛さ)
function chiliIcon(fill = '#D7261E') {
  return `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 3.5c.4 1.4 1.6 2.1 3 2.3" fill="none" stroke="#3E8B2F" stroke-width="2" stroke-linecap="round"/><path d="M16.5 6.5c2.6 2.4 1.7 7.4-2.3 10.6-3.3 2.6-7.7 3.6-10.7 3.4 2.6-1.3 5-3.3 6.7-6 1.8-2.8 2.5-5.4 2.6-7.4 1.1-.9 2.6-1.4 3.7-.6Z" fill="${fill}"/></svg>`;
}
