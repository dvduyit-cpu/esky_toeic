const {chromium}=require('./browser-check/node_modules/playwright-core');
const fs=require('fs'),crypto=require('crypto');
const base='http://toeic.localhost';
const marker='ui-portal-check-'+Date.now(),email=marker+'@example.test';
const passwords=Object.fromEntries(['toeic','vstep','course'].map(track=>[track,crypto.randomBytes(16).toString('hex')]));
fs.writeFileSync('storage/framework/portal-check-data.json',JSON.stringify({marker,email}));
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 const context=await browser.newContext({viewport:{width:1440,height:1000},locale:'vi-VN'});
 const page=await context.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));page.on('dialog',async dialog=>{errors.push('Unexpected native dialog: '+dialog.type());await dialog.dismiss();});
 const adminPassword=fs.readFileSync('.env','utf8').match(/^ESKY_ADMIN_PASSWORD="?([^"\r\n]+)"?$/m)[1];
 const dialog=()=>page.locator('dialog[open]').last();
 async function saveDialog(name){await dialog().getByRole('button',{name,exact:true}).click();await page.waitForFunction(()=>!document.querySelector('dialog[open]'));}
 async function login(track){await context.clearCookies();await page.goto(base+(track==='admin'?'/admin/login':'/exam/'+track+'/login'));await page.locator('#login-email').fill(track==='admin'?'admin@esky.local':email);await page.locator('#login-password').fill(track==='admin'?adminPassword:passwords[track]);await page.getByRole('button',{name:'Đăng nhập',exact:true}).click();await page.waitForURL(track==='admin'?'**/dashboard':'**/exam/'+track);}
 async function addQuestion(track,kind='mcq'){
  await page.getByRole('button',{name:'+ Thêm câu hỏi',exact:true}).click();await dialog().waitFor();
  if(track==='vstep')await page.locator('#q-skill').selectOption(kind==='essay'?'writing':kind==='speaking'?'speaking':'reading');
  if(track==='course'){await page.locator('#q-subject').selectOption({label:marker});await page.locator('#q-kind').selectOption(kind);}
  await page.locator('#q-content').fill(marker+'-'+track+'-'+kind);await page.locator('#q-points').fill(kind==='mcq'?'2':'10');
  if(kind==='mcq'){await page.locator('#q-option-A').fill('Correct answer');await page.locator('#q-option-B').fill('Other answer');}
  await saveDialog('Lưu câu hỏi');await page.locator('tbody tr').filter({hasText:marker+'-'+track+'-'+kind}).waitFor();
 }
 try{
  await page.goto(base+'/exam/login');await page.locator('.portal-card').nth(2).waitFor();await page.screenshot({path:'storage/framework/modules-portals.png',fullPage:true});
  await login('admin');
  await page.goto(base+'/manage/course/subjects');await page.getByRole('button',{name:'+ Thêm môn học',exact:true}).click();await page.locator('#subject-code').fill(marker);await page.locator('#subject-name').fill(marker);await saveDialog('Lưu môn học');
  for(const track of ['toeic','vstep','course']){
   const prefix=base+'/manage/'+track;
   await page.goto(prefix+'/users');await page.getByRole('button',{name:'+ Thêm tài khoản',exact:true}).click();await page.locator('#user-name').fill(marker);await page.locator('#user-email').fill(email);await page.locator('#user-password').fill(passwords[track]);await saveDialog('Lưu tài khoản');await page.locator('tbody tr').filter({hasText:email}).waitFor();
   await page.goto(prefix+'/questions');await addQuestion(track);if(track==='vstep'){await addQuestion(track,'essay');await addQuestion(track,'speaking');}
   const qcount=track==='vstep'?3:1;
   await page.goto(prefix+'/exams');await page.getByRole('button',{name:'+ Thêm đề thi',exact:true}).click();await page.locator('#exam-title').fill(marker+'-'+track);await page.locator('#exam-duration').fill('10');if(track==='course')await page.locator('#exam-subject').selectOption({label:marker});await saveDialog('Lưu đề thi');
   let row=page.locator('tbody tr').filter({hasText:marker+'-'+track});await row.getByRole('button',{name:'Câu hỏi',exact:true}).click();
   for(const kind of (track==='vstep'?['mcq','essay','speaking']:['mcq'])){
    const selector=page.locator('select[aria-label="Chọn câu hỏi thêm vào đề"]');const option=selector.locator('option').filter({hasText:marker+'-'+track+'-'+kind});await selector.selectOption(await option.getAttribute('value'));await dialog().getByRole('button',{name:'Thêm vào đề',exact:true}).click();await dialog().locator('.modal-list p').filter({hasText:marker+'-'+track+'-'+kind}).waitFor();
   }
   await dialog().getByRole('button',{name:'Đóng hộp thoại',exact:true}).click();await row.getByRole('button',{name:'Mở đề',exact:true}).click();await saveDialog('Mở đề');
   await page.goto(prefix+'/exam-accesses');await page.getByRole('button',{name:'+ Cấp quyền thi',exact:true}).click();const userOption=page.locator('#grant-user option').filter({hasText:email});await page.locator('#grant-user').selectOption(await userOption.getAttribute('value'));const examOption=page.locator('#grant-exam option').filter({hasText:marker+'-'+track});await page.locator('#grant-exam').selectOption(await examOption.getAttribute('value'));await saveDialog('Cấp quyền thi');await page.locator('tbody tr').filter({hasText:email}).waitFor();
   console.log('Modal account/question/exam/grant: '+track+' OK');
  }
  // Check edit and deletion modals with an unused question.
  await page.goto(base+'/manage/toeic/questions');await page.getByRole('button',{name:'+ Thêm câu hỏi',exact:true}).click();await page.locator('#q-content').fill(marker+'-temporary');await page.locator('#q-option-A').fill('A');await page.locator('#q-option-B').fill('B');await saveDialog('Lưu câu hỏi');let row=page.locator('tbody tr').filter({hasText:marker+'-temporary'});await row.getByRole('button',{name:'Sửa',exact:true}).click();await page.locator('#q-content').fill(marker+'-edited');await saveDialog('Lưu câu hỏi');row=page.locator('tbody tr').filter({hasText:marker+'-edited'});await row.getByRole('button',{name:'Xóa',exact:true}).click();await saveDialog('Xác nhận xóa');if(await page.locator('tbody tr').filter({hasText:marker+'-edited'}).count())throw new Error('Delete failed');
  await page.setViewportSize({width:390,height:844});
  for(const path of ['/manage/toeic/questions','/manage/vstep/users','/manage/vstep/exams','/manage/course/subjects','/manage/course/exam-accesses']){await page.goto(base+path);await page.locator('h1').waitFor();if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw new Error('Mobile overflow: '+path);}
  for(const track of ['toeic','vstep','course']){
   await login(track);await page.locator('.candidate-exams article').filter({hasText:marker+'-'+track}).getByRole('button',{name:'Bắt đầu thi',exact:true}).click();await saveDialog('Bắt đầu thi');await page.waitForURL('**/exam/'+track+'/attempts/*');
   await page.locator('input[type=radio][value=A]').first().check();
   if(track==='vstep'){
    await page.locator('textarea.question-response').fill('My writing answer for the VSTEP test.');
    const buffer=Buffer.alloc(44+16000);buffer.write('RIFF',0);buffer.writeUInt32LE(buffer.length-8,4);buffer.write('WAVEfmt ',8);buffer.writeUInt32LE(16,16);buffer.writeUInt16LE(1,20);buffer.writeUInt16LE(1,22);buffer.writeUInt32LE(8000,24);buffer.writeUInt32LE(16000,28);buffer.writeUInt16LE(2,32);buffer.writeUInt16LE(16,34);buffer.write('data',36);buffer.writeUInt32LE(16000,40);
    const upload=page.waitForResponse(response=>response.url().includes('/recordings/')&&response.request().method()==='POST');await page.locator('.recording-controls input[type=file]').setInputFiles({name:'voice.wav',mimeType:'audio/wav',buffer});const response=await upload;if(response.status()!==200)throw new Error('Speaking upload status '+response.status());await page.locator('.recording-controls audio').waitFor();
   }
   await page.locator('.save-status').filter({hasText:'Đã lưu'}).waitFor();await page.reload();await page.locator('input[type=radio][value=A]:checked').waitFor();if(track==='vstep'&&await page.locator('textarea.question-response').inputValue()!=='My writing answer for the VSTEP test.')throw new Error('Essay not restored');
   if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw new Error('Attempt mobile overflow');
   await page.screenshot({path:'storage/framework/module-attempt-'+track+'.png',fullPage:true});
   await page.getByRole('button',{name:'Nộp bài',exact:true}).click();await saveDialog('Xác nhận nộp bài');await page.locator('.result-card').waitFor();if(track==='vstep')await page.getByText('Đã nộp · Chờ giáo viên chấm',{exact:true}).waitFor();else await page.locator('.result-score').waitFor();console.log('Candidate save/restore/submit: '+track+' OK');
  }
  await login('admin');await page.goto(base+'/manage/vstep/results');const resultRow=page.locator('tbody tr').filter({hasText:marker+'-vstep'});await resultRow.getByRole('button',{name:'Xem / chấm bài',exact:true}).click();const scores=dialog().locator('input[type=number]');await scores.nth(0).fill('8');await scores.nth(1).fill('7');await saveDialog('Lưu điểm chấm');await resultRow.getByText('17',{exact:true}).waitFor();console.log('Manual essay/speaking grading: OK');
  await page.setViewportSize({width:1440,height:1000});await page.goto(base+'/dashboard');await page.screenshot({path:'storage/framework/modules-dashboard.png',fullPage:true});await page.goto(base+'/manage/vstep/questions');await page.screenshot({path:'storage/framework/modules-bank.png',fullPage:true});await page.getByRole('button',{name:'+ Thêm câu hỏi',exact:true}).click();await page.screenshot({path:'storage/framework/modules-question-modal.png',fullPage:true});
  if(errors.length)throw new Error(errors.join('\n'));console.log('PASS: three isolated modules, all CRUD in native-accessible app modals, mobile layouts, real audio upload, independent accounts, manual grading, no browser errors');
 }catch(error){await page.screenshot({path:'storage/framework/modules-failure.png',fullPage:true});throw error;}finally{await browser.close();}
})().catch(error=>{console.error(error.message);process.exit(1);});
