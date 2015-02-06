// Image upload
imageInput   = $("#single-image-input");
imagePreview = $('img#preview');

imageInput.fileinput({
    uploadUrl: upload_path,
    uploadExtraData: {"_token": csrf_token},
    browseIcon: '<i class="fa fa-folder-open"></i> ',
    layoutTemplates: {
        icon: '<span class="fa fa-picture-o kv-caption-icon"></span> '
    },
    allowedFileTypes: ['image'],
    showPreview: false
});

imageInput.on('fileloaded', function(event, file, previewId, index, reader) {
    console.log("fileloaded");
    readURL(this);
    $(this).fileinput('upload');
});

imageInput.on('fileclear', function(event) {
    console.log("fileclear");
    imagePreview.attr('src', 'data:image/png;base64,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');
});

imageInput.on('filebatchuploadsuccess', function(event, data, previewId, index) {
    if (data['response'] && data['response']['redirect']) {
        window.location.replace(data['response']['redirect'])
    }
});


// Drag & drop
var uploadContainer = $('body');

uploadContainer.on('dragenter', function (e)
{
    // Prevent default browser behavior
    e.stopPropagation();
    e.preventDefault();
});

uploadContainer.on('dragover', function (e)
{
    // Prevent default browser behavior
    e.stopPropagation();
    e.preventDefault();
});

uploadContainer.on('drop', function (e)
{
    // Prevent default browser behavior
    e.preventDefault();

    // Render the preview image
    var files = e.originalEvent.dataTransfer.files;
    console.log(files);
    readURL(files);

    // Register the drag and drop upload
    imageInput.fileinput('change', e, 'dragdrop');
});

// Render the preview image
function readURL(input) {

    // Set up a FileReader instance
    var reader = new FileReader();
    if (input.files && input.files[0]) {
        var file = input.files[0];
    } else if (input[0]) {
        var file = input[0];
    }

    // Render the preview
    if (file) {
        reader.onload = function (e) {
            imagePreview.attr('src', e.target.result);
        }

        reader.readAsDataURL(file);
    }

}

// Select input text on focus
$(".select-on-focus").focus(function() {
    this.select();
});