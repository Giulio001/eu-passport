(function (blocks, element, components, editor) {
  const h = element.createElement;
  blocks.registerBlockType('giuliomax/product-passport', {
    apiVersion: 3, title: 'Scheda prodotto Giuliomax', icon: 'id', category: 'widgets',
    attributes: { uuid: {type: 'string', default: ''}, view: {type: 'string', default: 'button'} },
    edit: ({attributes, setAttributes}) => h('div', editor.useBlockProps(),
      h(components.TextControl, {label: 'Identificatore della scheda (UUID)', value: attributes.uuid, onChange: uuid => setAttributes({uuid})}),
      h(components.SelectControl, {label: 'Mostra', value: attributes.view, options: [{label:'Pulsante',value:'button'},{label:'QR code',value:'qr'},{label:'Scheda completa',value:'sheet'}], onChange: view=>setAttributes({view})})
    ), save: () => null
  });
})(wp.blocks, wp.element, wp.components, wp.blockEditor);
