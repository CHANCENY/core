 import {
     ClassicEditor,
     Essentials,
     Paragraph,
     Heading,
     Bold,
     Italic,
     Font,
     List,
     ListProperties,
     Image,
     ImageToolbar,
     ImageUpload,
     ImageInsert,
     Base64UploadAdapter,
     SourceEditing
    } from 'ckeditor5';

    if (document.querySelectorAll('textarea')) {
        const textarea = document.querySelectorAll('textarea');
        Array.from(textarea).forEach((element) => {

            if (element.classList.contains('editor')) {

                ClassicEditor
                .create( element, {
                    licenseKey: 'GPL',
                        plugins: [
                            Essentials,
                            Paragraph,
                            Heading,
                            Bold,
                            Italic,
                            Font,
                            List,
                            ListProperties,
                            Image,
                            ImageToolbar,
                            ImageUpload,
                            ImageInsert,
                            Base64UploadAdapter,
                            SourceEditing
                        ],
                    toolbar: [
                        'heading', '|',
                        'bold', 'italic', '|',
                        'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', '|',
                        'bulletedList', 'numberedList', '|',
                        'insertImage', '|',
                        'sourceEditing', '|',
                        'undo', 'redo'
                    ],
                    image: {
                        toolbar: ['imageTextAlternative', 'imageStyle:full', 'imageStyle:side']
                    },
                    codeBlock: {
                        languages: [
                            { language: 'plaintext', label: 'Plain text' }, // The default language.
                            { language: 'c', label: 'C' },
                            { language: 'cs', label: 'C#' },
                            { language: 'cpp', label: 'C++' },
                            { language: 'css', label: 'CSS' },
                            { language: 'diff', label: 'Diff' },
                            { language: 'html', label: 'HTML' },
                            { language: 'java', label: 'Java' },
                            { language: 'javascript', label: 'JavaScript' },
                            { language: 'php', label: 'PHP' },
                            { language: 'python', label: 'Python' },
                            { language: 'ruby', label: 'Ruby' },
                            { language: 'typescript', label: 'TypeScript' },
                            { language: 'xml', label: 'XML' }
                        ]
                    }
                })
                .then( editor => {
                    window.editor = editor;
                } )
                .catch( error => {
                    console.error( error );
                });

            }
            
        })
    }


 function reloadCk() {
     if (document.querySelectorAll('textarea')) {
         const textarea = document.querySelectorAll('textarea');
         Array.from(textarea).forEach((element) => {

             if (element.classList.contains('editor')) {

                 ClassicEditor
                     .create( element, {
                         licenseKey: 'GPL',
                         plugins: [ Essentials, Paragraph, Bold, Italic, Font,CodeBlock ],
                         toolbar: [
                             'undo', 'redo', '|', 'bold', 'italic', '|',
                             'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor',
                             'codeBlock'
                         ],
                         codeBlock: {
                             languages: [
                                 { language: 'plaintext', label: 'Plain text' }, // The default language.
                                 { language: 'c', label: 'C' },
                                 { language: 'cs', label: 'C#' },
                                 { language: 'cpp', label: 'C++' },
                                 { language: 'css', label: 'CSS' },
                                 { language: 'diff', label: 'Diff' },
                                 { language: 'html', label: 'HTML' },
                                 { language: 'java', label: 'Java' },
                                 { language: 'javascript', label: 'JavaScript' },
                                 { language: 'php', label: 'PHP' },
                                 { language: 'python', label: 'Python' },
                                 { language: 'ruby', label: 'Ruby' },
                                 { language: 'typescript', label: 'TypeScript' },
                                 { language: 'xml', label: 'XML' }
                             ]
                         }
                     })
                     .then( editor => {
                         window.editor = editor;
                     } )
                     .catch( error => {
                         console.error( error );
                     });

             }

         })
     }
 }
window.reloadCk = reloadCk;