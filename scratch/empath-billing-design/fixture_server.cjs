const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const root = __dirname;
const source = path.resolve('C:/xampp/htdocs/empath/public');
let lastPayload = null;
let visitAttempts = 0;
let billId = null;
let patient = { id: 11, uhid: 11, name: 'Mr. Test Patient', age: 30, gender: 'Male', mobile: '9999900010', catId: '1' };
const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://127.0.0.1:8098');
  const json = (body, status = 200) => { res.writeHead(status, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(body)); };
  if (url.pathname.startsWith('/assets/')) {
    let file = path.join(root, 'public', url.pathname);
    if (!fs.existsSync(file)) file = path.join(source, url.pathname);
    if (!file.startsWith(source) && !file.startsWith(root)) return res.end();
    if (!fs.existsSync(file)) { res.writeHead(404); return res.end(); }
    const ext = path.extname(file);
    res.writeHead(200, { 'Content-Type': ({ '.js': 'application/javascript', '.css': 'text/css', '.png': 'image/png', '.svg': 'image/svg+xml' })[ext] || 'application/octet-stream' });
    return fs.createReadStream(file).pipe(res);
  }
  if (req.method === 'POST') {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      const data = Object.fromEntries(new URLSearchParams(body));
      if (url.pathname.endsWith('/register-patient')) {
        patient = { ...patient, name: `${data.prefix} ${data.Fname} ${data.Lname}`.trim() };
        return json({ success: true, patient: { id: 11, prefix: data.prefix, Fname: data.Fname, Lname: data.Lname, sex: data.gender, age: data.age, mobile_no: data.mobile_no, i_cover: data.i_cover } });
      }
      if (url.pathname.endsWith('/prepare-visit')) {
        visitAttempts++;
        return json({ success: true, visit_id: 21, visit_no: 1, draft_bill_id: billId, registration_charge: 100 });
      }
      if (url.pathname.endsWith('/save')) {
        lastPayload = data;
        billId = 31;
        console.log(JSON.stringify({ saved: data.action === 'save_as_draft' ? 'draft' : 'final', visit: data.prepared_visit_id, bill: data.bill_no, paid: data.total_paid }));
        return json({ success: true, bill_id: billId });
      }
      return json({ success: true });
    });
    return;
  }
  if (url.pathname.includes('check-duplicate-mobile')) return json({ patients: [] });
  if (url.pathname.endsWith('/registration/search')) return json({ patients: [patient] });
  if (url.pathname.includes('/getconsultcharges/')) return json({ charges: [{ id: 1, comment: 'New consultation', ch: 300 }], total_visit: 0 });
  if (url.pathname.includes('/getcharges_details/')) return json({ charges: { charges: url.pathname.includes('/8/') ? 100 : 200, is_editable: false }, service: 'Test service' });
  if (url.pathname.includes('/draft-bill/')) return json({ success: true, draft_bill: { bill_id: 31, visit_id: 21, doctor_id: 2, consult_charge: 300, consult_primary_id: 40, reg_charge: 100, reg_primary_id: 41, extras: [], bill_discount: 0 } });
  if (url.pathname.includes('/billing/bill/')) return json({ id: 31, no: '31', patient, doctor: 'Test Doctor', doctor_id: 2, paid: Number(lastPayload?.total_paid || 0), due: Number(lastPayload?.total || 0) - Number(lastPayload?.total_paid || 0), total: Number(lastPayload?.total || 0), items: [], payments: [], movements: [] });
  if (url.pathname.endsWith('/billing/list')) return json({ bills: [], total: 0, summary: {} });
  if (url.pathname === '/test-calls') return json({ lastPayload, visitAttempts });
  res.writeHead(200, { 'Content-Type': 'text/html' });
  fs.createReadStream(path.join(root, 'runtime/fixture.html')).pipe(res);
});
server.listen(8098, '127.0.0.1', () => console.log('Isolated billing fixture: http://127.0.0.1:8098'));
