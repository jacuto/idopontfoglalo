const {chromium}=require('playwright');const assert=require('node:assert/strict');
const base=process.env.RAERO_TEST_URL||'http://127.0.0.1:8088';
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 const page=await browser.newPage({viewport:{width:1365,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));let logged=false,profileWrites=0,submitted;
 const guest={id:10,name:'Google Vendég',email:'social@example.com',phone:'',avatar:null,bookings:[],providers:[{id:1,name:'Első szolgáltató'},{id:2,name:'Másik szolgáltató'}],allBookings:[
 {id:'one',start:'2035-03-12T09:00:00Z',status:'pending',token:'first',serviceSnapshot:[{id:'cut',name:'Női hajvágás'}],provider:{id:1,name:'Első szolgáltató'}},
 {id:'two',start:'2020-03-12T09:00:00Z',status:'confirmed',attendance:'attended',token:'second',serviceSnapshot:[{id:'massage',name:'Masszázs'}],provider:{id:2,name:'Másik szolgáltató'}}]};
 await page.route('**/api/auth/providers*',r=>r.fulfill({json:{google:true,facebook:true}}));
 await page.route('**/api/guest**',r=>{
  const path=new URL(r.request().url()).pathname;
  if(path==='/api/guest/profile'){assert.equal(r.request().method(),'PATCH');assert.ok(r.request().headers()['x-csrf-token']);profileWrites++;Object.assign(guest,r.request().postDataJSON());return r.fulfill({json:{ok:true}});}
  if(path==='/api/guest')return r.fulfill({status:logged?200:401,json:logged?guest:{error:'Vendégbelépés szükséges.'}});
  return r.continue();
 });
 // External identity is simulated here; PHP tests separately exercise actual callback handling with mocked provider HTTP.
 await page.route('**/auth/google?*',r=>{assert.equal(new URL(r.request().url()).searchParams.get('returnTo'),'booking');logged=true;return r.fulfill({status:302,headers:{location:base+'/foglalas?provider=1#public-form'}});});
 await page.route('**/api/bookings?*',r=>{if(r.request().method()!=='POST')return r.continue();submitted=r.request().postDataJSON();return r.fulfill({status:201,json:{id:'booked',status:'pending',token:'new'}});});
 await page.goto(base+'/foglalas?provider=1');await page.locator('#booking-form').waitFor();await page.getByRole('button',{name:'Időpontot foglalok',exact:true}).first().click();
 const date=new Date();date.setDate(date.getDate()+4);await page.locator('#booking-date').fill(date.toISOString().slice(0,10));await page.locator('#available-slots button').first().click();
 const before=await page.locator('[name=start]').inputValue();const services=await page.locator('[name=services]:checked').evaluateAll(items=>items.map(x=>x.value));
 await page.locator('.guest-login-link a').click();await page.locator('#guest-social a[href^="/auth/google"]').click();await page.locator('.guest-autofill').waitFor();
 await page.waitForFunction(value=>document.querySelector('[name=start]').value===value,before);assert.match(page.url(),/\/foglalas\?provider=1/);
 assert.equal(await page.locator('[name=name]').inputValue(),'Google Vendég');assert.equal(await page.locator('[name=email]').inputValue(),'social@example.com');
 assert.deepEqual(await page.locator('[name=services]:checked').evaluateAll(items=>items.map(x=>x.value)),services);
 await page.locator('[name=phone]').fill('+36 30 111 2222');await page.getByRole('button',{name:'Időpont lefoglalása →'}).click();await page.locator('.success').waitFor();assert.equal(profileWrites,1);assert.equal(submitted.phone,guest.phone);
 await page.goto(base+'/foglalas?provider=1');await page.locator('.guest-autofill').waitFor();assert.equal(await page.locator('[name=phone]').inputValue(),'+36 30 111 2222');
 await page.locator('.guest-login-link a').click();await page.locator('#avatar-form').waitFor();assert.equal(await page.locator('.guest-booking').count(),2);
 assert.equal(await page.locator('.guest-booking a[href="/foglalas?provider=2"]').innerText(),'Másik szolgáltató');assert.equal(await page.locator('.guest-booking a[href="/modositas?provider=2&token=second"]').count(),1);
 assert.equal(await page.locator('#conversation,#message-form').count(),0);assert.equal(await page.getByRole('link',{name:'Új foglalás',exact:true}).count(),0);assert.equal(await page.getByRole('button',{name:'Üzenet az üzemeltetőnek',exact:true}).count(),0);
 await page.locator('.guest-review').click();await page.locator('#review-form').waitFor();let reviewProvider;
 await page.route('**/api/guest/bookings/two/review?*',r=>{reviewProvider=new URL(r.request().url()).searchParams.get('provider');return r.fulfill({status:201,json:{ok:true}});});
 await page.locator('#review-form textarea').fill('Szuper szolgáltatás');await page.getByRole('button',{name:'Értékelés mentése',exact:true}).click();await page.waitForFunction(()=>!document.querySelector('#modal').open);assert.equal(reviewProvider,'2');
 await page.setViewportSize({width:390,height:844});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:'work/guest-history-mobile.png',fullPage:true});assert.deepEqual(errors,[]);
 await page.route('**/api/messages**',r=>r.fulfill({json:r.request().method()==='GET'?[]:{ok:true}}));
 await page.goto(base+'/uzenetek?provider=1');await page.getByRole('heading',{name:'Üzenetek: Első szolgáltató',exact:true}).waitFor();await page.locator('#message-form').waitFor();
 console.log('PASS: return to booking; restore service/day/slot; prefill own contact; remember phone; history across providers; scoped reviews; no ambiguous profile actions; mobile.');await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
