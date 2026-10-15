const { chromium } = require('./browser-check/node_modules/playwright-core');
const fs = require('fs');
(async () => {
 const browser = await chromium.launch({ channel: 'msedge', headless: true });
 const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
 const errors = [];
 page.on('pageerror', error => errors.push(error.message));
 const base = 'http://toeic.localhost';
 await page.goto(base + '/login');
 await page.screenshot({ path: 'storage/framework/toeic-login-desktop.png', fullPage: true });
 await page.locator('#login-email').fill('admin@esky.local');
 const password = fs.readFileSync('.env', 'utf8').match(/^ESKY_ADMIN_PASSWORD="?([^"\r\n]+)"?$/m)[1];
 await page.locator('#login-password').fill(password);
 await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
 await page.waitForURL('**/dashboard');
 await page.screenshot({ path: 'storage/framework/toeic-dashboard-desktop.png', fullPage: true });
 await page.getByRole('button', { name: 'Mở hoặc ẩn menu', exact: true }).click();
 if (!await page.locator('.shell').evaluate(el => el.classList.contains('menu-collapsed'))) throw new Error('Desktop menu did not collapse');
 await page.reload();
 await page.waitForSelector('.menu-collapsed');
 await page.getByRole('button', { name: 'Mở hoặc ẩn menu', exact: true }).click();
 for (const path of ['/users', '/questions', '/exams']) {
  await page.goto(base + path);
  await page.locator('h1').waitFor();
  await page.locator('table tbody tr').first().waitFor();
  console.log('Desktop page OK: ' + path);
 }
 await page.setViewportSize({ width: 390, height: 844 });
 for (const path of ['/dashboard', '/users', '/questions', '/exams']) {
  await page.goto(base + path);
  if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw new Error('Horizontal overflow: ' + path);
  console.log('Mobile page OK: ' + path);
 }
 await page.goto(base + '/dashboard');
 await page.screenshot({ path: 'storage/framework/toeic-dashboard-mobile.png', fullPage: true });
 await page.getByRole('button', { name: 'Mở hoặc ẩn menu', exact: true }).click();
 await page.locator('.sidebar .nav-link[href="/questions"]').click();
 await page.waitForURL('**/questions');
 if (await page.locator('.sidebar-overlay').count()) throw new Error('Mobile menu remained open after navigation');
 await page.locator('.profile-menu summary').click();
 await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click();
 await page.waitForURL('**/login');
 await page.screenshot({ path: 'storage/framework/toeic-login-mobile.png', fullPage: true });
 if (errors.length) throw new Error(errors.join('\n'));
 console.log('PASS: login/logout, desktop menu persistence, mobile navigation, 4 pages without overflow, no browser errors');
 await browser.close();
})().catch(error => { console.error(error); process.exit(1); });
