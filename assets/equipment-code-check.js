/* Preflight keeps the filled form and selected photo intact when a code conflicts. */
document.addEventListener('submit', async event => {
    const form=event.target;
    const action=form.querySelector('[name="action"]')?.value;
    if(!['create','update','create_item','update_item'].includes(action))return;
    const input=form.querySelector('[name="equipment_code"], [name="asset_code"]');
    if(!input || !input.value.trim())return;
    event.preventDefault();
    if(form.dataset.checkingCode==='1')return;
    form.dataset.checkingCode='1';
    const buttons=Array.from(form.querySelectorAll('[type="submit"]'));
    buttons.forEach(b=>b.disabled=true);
    try{
        const url=new URL('actions/check_equipment_code.php',window.location.href);
        const formURL=new URL(form.action,window.location.href);
        url.searchParams.set('code',input.value);
        url.searchParams.set('year',form.querySelector('[name="fiscal_year"]')?.value || formURL.searchParams.get('year') || form.dataset.codeYear);
        url.searchParams.set(action.endsWith('_item')?'inspection_id':'registry_id',form.querySelector('[name="id"]')?.value || '0');
        const response=await fetch(url,{headers:{Accept:'application/json'}});
        const data=await response.json();
        if(!response.ok)throw new Error(data.error || 'ตรวจสอบรหัสไม่สำเร็จ');
        if(data.duplicate){
            const item=data.matches[0];
            alert(`รหัสครุภัณฑ์ซ้ำกับ “${item.item_name}” ในบัญชีตรวจปี ${item.fiscal_year}\nกรุณาตรวจสอบและแก้รหัสก่อนบันทึก`);
            input.focus();return;
        }
        HTMLFormElement.prototype.submit.call(form);
    }catch(error){alert(error.message || 'ตรวจสอบรหัสไม่สำเร็จ กรุณาลองอีกครั้ง');}
    finally{delete form.dataset.checkingCode;buttons.forEach(b=>b.disabled=false);}
});
