(() => {
  const W=window.DesktopWorkspaces;
  window.configureDesktopWidget=(card,name,order,preferences,save,render)=>{
    card.dataset.widget=name;card.classList.toggle('is-wide-widget',!!preferences[name]?.wide);
    const label=W.field('wide_widget','checkbox',!!preferences[name]?.wide);label.className='workspace-widget-size';label.prepend(label.querySelector('input'));label.querySelector('input').onchange=event=>{preferences[name]={...preferences[name],wide:event.target.checked};save();render();};card.append(label);
    const heading=card.querySelector('h2');heading.draggable=true;heading.title=W.t('drag_widget');
    heading.addEventListener('dragstart',event=>event.dataTransfer.setData('application/x-atapin-widget',name));
    card.addEventListener('dragover',event=>{if([...event.dataTransfer.types].includes('application/x-atapin-widget'))event.preventDefault();});
    card.addEventListener('drop',event=>{const source=event.dataTransfer.getData('application/x-atapin-widget');if(!order.includes(source)||source===name)return;event.preventDefault();const next=order.filter(key=>key!==source);next.splice(next.indexOf(name),0,source);next.forEach((key,index)=>preferences[key]={...preferences[key],order:index});save();render();});
  };
})();
