const {chromium}=require('playwright'),assert=require('node:assert/strict');
const base=process.env.RAERO_TEST_URL||'http://127.0.0.1:8088';
(async()=>{
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',args:['--no-sandbox']}),page=await browser.newPage({viewport:{width:1280,height:900},timezoneId:'Europe/Budapest'}),errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(12000);
 await page.goto(base+'/foadmin');await page.locator('#platform-login [name=password]').fill('Browser-platform-password');await page.locator('#platform-login button').click();await page.locator('.platform-shell').waitFor();
 let invoices=[],writes=0;
 await page.route('**/api/platform/payments*',r=>r.fulfill({json:[{id:77,provider_id:1,amount:4000,reference:'browser-manual-test'}]}));
 await page.route('**/api/platform/providers*',r=>r.fulfill({json:[{id:1,name:'Teszt Szalon'}]}));
 await page.route('**/api/platform/invoices**',async r=>{
  if(r.request().method()==='PUT'){const data=r.request().postDataJSON();assert.equal(data.paymentId,77);assert.equal(data.issuedOn,'2026-11-10');invoices=[{id:88,payment_id:77,provider_id:1,source:'manual',status:'issued',number:data.number,issued_on:data.issuedOn,reference:null}];writes++;return r.fulfill({json:{ok:true}});}
  return r.fulfill({json:invoices});
 });
 await page.getByRole('button',{name:'Előfizetési számlák',exact:true}).click();
 await page.getByRole('button',{name:'Számlaadatok kézi rögzítése',exact:true}).click();
 await page.locator('#manual-platform-invoice [name=number]').fill('TEST-2026-001');await page.locator('#manual-platform-invoice [name=issuedOn]').fill('2026-11-10');await page.locator('#manual-platform-invoice .primary').click();
 await page.getByRole('button',{name:'Számlaadatok szerkesztése',exact:true}).waitFor();assert.match(await page.locator('#platform-content').innerText(),/TEST-2026-001.*2026\.11\.10\./);assert.equal(await page.locator('#modal a[href$="/pdf"]').count(),0);assert.equal(await page.getByRole('button',{name:'Számla kiállítása Billingóval',exact:true}).count(),0);
 await page.getByRole('button',{name:'Számlaadatok szerkesztése',exact:true}).click();assert.equal(await page.locator('[name=number]').inputValue(),'TEST-2026-001');await page.locator('[name=number]').fill('TEST-2026-002');await page.locator('#manual-platform-invoice .primary').click();await page.getByRole('button',{name:'Számlaadatok szerkesztése',exact:true}).waitFor();assert.equal(writes,2);
 const detail=await page.evaluate(i=>platformInvoiceDetails(i),invoices[0]);assert.match(detail,/TEST-2026-002/);assert.match(detail,/2026\.11\.10\./);assert.ok(!detail.includes('/pdf'));
 await page.screenshot({path:'work/manual-platform-invoices-desktop.png'});await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await page.screenshot({path:'work/manual-platform-invoices-mobile.png'});assert.deepEqual(errors,[]);await browser.close();console.log('PASS: manual invoice number/date creation and editing, Hungarian date, no invalid PDF or duplicate issue action; mobile fits.');
})().catch(e=>{console.error(e);process.exit(1)});
