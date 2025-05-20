jQuery(function($){
    var frame, $list = $('#pam-gallery-list'), $input = $('#pam_images');

    // Open media frame
    $('#pam-add-images').on('click', function(e){
        e.preventDefault();
        if ( frame ) { frame.open(); return; }
        frame = wp.media({
            title: 'Select Images',
            library: { type: 'image' },
            button: { text: 'Add to gallery' },
            multiple: true
        });
        frame.on('select', function(){
            var selection = frame.state().get('selection'),
                ids = $input.val() ? $input.val().split(',') : [];
            selection.map(function( attachment ){
                attachment = attachment.toJSON();
                if ( ids.indexOf( String(attachment.id) ) === -1 ) {
                    ids.push( String(attachment.id) );
                    $list.append(
                        '<li data-id="'+attachment.id+'">'+
                            '<img src="'+attachment.sizes.thumbnail.url+'"/>'+
                            '<button class="remove-image">×</button>'+
                        '</li>'
                    );
                }
            });
            $input.val( ids.join(',') );
        });
        frame.open();
    });

    // Enable sorting
    $list.sortable({
        update: function(){
            var ordered = [];
            $list.find('li').each(function(){
                ordered.push( $(this).data('id') );
            });
            $input.val( ordered.join(',') );
        }
    });

    // Remove image
    $list.on('click', '.remove-image', function(){
        var $li = $(this).closest('li'),
            id  = String( $li.data('id') ),
            ids = $input.val().split(',');
        ids = ids.filter(function(i){ return i !== id; });
        $input.val( ids.join(',') );
        $li.remove();
    });
});
