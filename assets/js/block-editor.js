(function(blocks, element, blockEditor, components) {
    const el = element.createElement;
    const { registerBlockType } = blocks;
    const { InspectorControls } = blockEditor;
    const { PanelBody, SelectControl } = components;

    registerBlockType('tempmail-pro/inbox', {
        title: 'TempMail Pro',
        icon: 'email-alt',
        category: 'widgets',
        description: 'Embed the TempMail Pro inbox widget.',
        attributes: {
            theme: { type: 'string', default: 'dark' },
        },
        edit: function(props) {
            const { attributes, setAttributes } = props;
            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: 'Display', initialOpen: true },
                        el(SelectControl, {
                            label: 'Theme',
                            value: attributes.theme,
                            options: [
                                { label: 'Dark', value: 'dark' },
                                { label: 'Light', value: 'light' },
                            ],
                            onChange: (val) => setAttributes({ theme: val }),
                        })
                    )
                ),
                el('div', { key: 'preview', className: 'tmpmp-block-preview', style: { padding: '20px', background: attributes.theme === 'dark' ? '#0f172a' : '#f1f5f9', borderRadius: '12px', textAlign: 'center', color: attributes.theme === 'dark' ? '#f1f5f9' : '#0f172a' } },
                    el('div', null, '📧'),
                    el('p', { style: { fontWeight: '700', margin: '8px 0 4px' } }, 'TempMail Pro Widget'),
                    el('p', { style: { fontSize: '12px', opacity: '.7' } }, 'Theme: ' + attributes.theme)
                )
            ];
        },
        save: function() { return null; }, // Server-side rendered
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components);
