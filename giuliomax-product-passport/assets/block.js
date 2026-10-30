(function (blocks, element, components, editor) {
  const h = element.createElement;
  blocks.registerBlockType('giuliomax/product-passport', {
    apiVersion: 3, title: wp.i18n.__("Giuliomax product sheet", 'giuliomax-product-passport'), icon: 'id', category: 'widgets',
    attributes: { uuid: {type: 'string', default: ''}, view: {type: 'string', default: 'button'} },
    edit: ({attributes, setAttributes}) => h('div', editor.useBlockProps(),
      h(components.TextControl, {label: wp.i18n.__("Sheet identifier (UUID)", 'giuliomax-product-passport'), value: attributes.uuid, onChange: uuid => setAttributes({uuid})}),
      h(components.SelectControl, {label: wp.i18n.__("Display", 'giuliomax-product-passport'), value: attributes.view, options: [{label:wp.i18n.__("Button", 'giuliomax-product-passport'),value:'button'},{label:wp.i18n.__("QR code", 'giuliomax-product-passport'),value:'qr'},{label:wp.i18n.__("Full sheet", 'giuliomax-product-passport'),value:'sheet'}], onChange: view=>setAttributes({view})})
    ), save: () => null
  });
})(wp.blocks, wp.element, wp.components, wp.blockEditor);
