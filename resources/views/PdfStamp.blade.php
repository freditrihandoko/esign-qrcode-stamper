<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        {{-- <script src="https://cdn.tailwindcss.com"></script> --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <style>
        #canvas-container {
            position: relative;
            width: max-content;
            height: max-content;
            padding: 0px;
        }
        .draggable {
            display: none;
            width: 87px;
            height: 87px;
            background-color: #9465ab;
            touch-action: none;
            user-select: none;
            text-align: center;
            padding: 30px 0;
            color: white;
            position: absolute;
        }
    </style>
    <body class="antialiased">
        <div class="relative sm:flex sm:justify-center sm:items-center min-h-screen bg-dots-darker bg-center bg-gray-100 dark:bg-dots-lighter dark:bg-gray-900 selection:bg-red-500 selection:text-white">
            <div class="max-w-7xl mx-auto p-6 lg:p-8">
                <div class="flex justify-center dark:text-white">
                    <form action="" method="post" enctype="multipart/form-data">
                        @csrf
                        <!-- component -->
                        <div class="flex w-full items-center justify-center bg-grey-lighter">
                            <label
                                class="w-64 flex flex-col items-center px-4 py-6 bg-white dark:bg-grey-lighter text-indigo-700 rounded-lg shadow-lg tracking-wide uppercase border border-blue cursor-pointer hover:bg-indigo-500 hover:text-white">
                                {{-- <svg class="w-8 h-8" fill="black" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M16.88 9.1A4 4 0 0 1 16 17H5a5 5 0 0 1-1-9.9V7a3 3 0 0 1 4.52-2.59A4.98 4.98 0 0 1 17 8c0 .38-.04.74-.12 1.1zM11 11h3l-4-4-4 4h3v3h2v-3z" />
                                </svg> --}}
                                <span class="mt-2 text-base dark:text-black leading-normal">{{ $qrCode->document->title }}</span>
                                <span class="mt-2 text-base dark:text-black leading-normal">{{ $qrCode->document->document_number }}</span>
                                {{-- <input type='file' name="pdf-file" class="hidden" id="document-result" accept=".pdf" required/> --}}
                                <input type="hidden" name="pdf-file" id="document-result" value="{{ Storage::url($qrCode->document->file_path) }}">
                                <hr>
                            </label>
                
                        </div>

                        <div class="flex w-full items-center items-center justify-center mt-3">
                            <input class="w-100 text-black dark:text-white bg-white dark:bg-black flex flex-col py-2 px-6 rounded" type="text" id="pageNumber" name="pageNumber" value="-" readonly placeholder="Page number">
                        </div>

                        <div class="flex w-full items-center inline-flex items-center justify-center mt-3 mb-3">
                            <button type="button" class="w-100 flex flex-col items-center text-indigo-700 border border-indigo-600 py-2 px-6 gap-2 rounded" id="prev">< Previous</button>
                            <button type="button" class="w-100 flex flex-col items-center text-indigo-700 border border-indigo-600 py-2 px-6 gap-2 rounded" id="next">Next ></button>
                        </div>
                        <span class="text-indigo-500 italic">Drag & drop QR dibawah sesuai posisi yang di inginkan</span>

                        <hr>
                        <div class="flex w-full bg-grey-lighter mt-3" id="canvas-container">
                            <div class="draggable"> QR </div>
                            <canvas class="border-solid border-2 dark:border-zinc-50" id="pdf-canvas"> ~ PDF ~</canvas>
                        </div>
                        <input type="hidden" id="stampX" name="stampX">
                        <input type="hidden" id="stampY" name="stampY">
                        <input type="hidden" id="canvasHeight" name="canvasHeight">
                        <input type="hidden" id="canvasWidth" name="canvasWidth">

                        {{-- btn --}}
                        <div class="flex w-full justify-center mt-6"> {{-- Adjusted container styles --}}
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ease-in-out">
                                Submit
                            </button>
                        </div>
                    </form>
                </div>
                <div class="bottom-0 left-0 right-0 mt-2 z-40 px-4 py-3 text-center text-white bg-gray-800">
                    <a href="https://github.com/TheArKaID/laravel-stamper" class="group inline-flex items-center hover:text-gray-700 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">
                      Code Laravel Stamper By : TheArKaID
                    </a>
                   
                </div>
            </div>
        </div>
    </body>

    <script type="module" src='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.269/pdf.min.mjs'></script>
    <script type="module" src='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.269/pdf.worker.min.mjs'></script>

    <script>
        // Predefine the variables
        var pdfDoc = null,
        pageNum = 1,
        pageRendering = false,
        pageNumPending = null,
        scale = 1.5,
        canvas = document.getElementById('pdf-canvas'),
        ctx = canvas.getContext('2d');

        document.querySelector("#document-result").addEventListener("change", async function(e){
            var file = e.target.files[0]
            if(file.type != "application/pdf"){
                alert(file.name, "is not a pdf file.")
                return
            }

            var fileReader = new FileReader();  

            fileReader.onload = async function() {
                var typedarray = new Uint8Array(this.result);

                const loadingTask = pdfjsLib.getDocument(typedarray);
                loadingTask.promise.then(pdf => {
                    // Set pdfDoc to the PDFJS object so we can reference it globally
                    pdfDoc = pdf
                    // Get Page number
                    pageNum = pdfDoc.numPages

                    document.getElementById('pageNumber').value = pageNum;
                    // Last page rendering
                    renderPage(pageNum);
                });
            };

            fileReader.readAsArrayBuffer(file);

            document.getElementsByClassName('draggable')[0].style.display = 'block';
        })
        
        /**
         * Get page info from document, resize canvas accordingly, and render page.
         * @param num Page number.
         */
         function renderPage(num) {
            pageRendering = true;
            // Using promise to fetch the page
            pdfDoc.getPage(num).then(function(page) {
                var viewport = page.getViewport({scale: scale});
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                document.getElementById('canvasHeight').value = viewport.height;
                document.getElementById('canvasWidth').value = viewport.width;

                // Render PDF page into canvas context
                var renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                var renderTask = page.render(renderContext);

                // Wait for rendering to finish
                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        // New page rendering is pending
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });
        }

        /**
         * If another page rendering in progress, waits until the rendering is
         * finised. Otherwise, executes rendering immediately.
         */
        function queueRenderPage(num) {
            document.getElementById('pageNumber').value = num;
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        /**
         * Displays previous page.
         */
        function onPrevPage() {
            if (pageNum <= 1) {
                return;
            }
            pageNum--;

            queueRenderPage(pageNum);
        }
        document.getElementById('prev').addEventListener('click', onPrevPage);

        /**
         * Displays next page.
         */
        function onNextPage() {
            if (pageNum >= pdfDoc.numPages) {
                return;
            }
            pageNum++;
            queueRenderPage(pageNum);
        }
        document.getElementById('next').addEventListener('click', onNextPage);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/interactjs@1.10.20/dist/interact.min.js"></script>

    <script>
        const position = { x: 0, y: 0 }
        interact('.draggable').draggable({
            listeners: {
                move (event) {
                    position.x += event.dx
                    position.y += event.dy

                    event.target.style.transform =
                        `translate(${position.x}px, ${position.y}px)`
                },
                end (event) {
                    var style = window.getComputedStyle(event.target);
                    var matrix = new WebKitCSSMatrix(style.transform);

                    console.log(matrix.m41, matrix.m42)
                    document.getElementById('stampX').value = matrix.m41;
                    document.getElementById('stampY').value = matrix.m42;
                }
            },
            inertia: true,
            modifiers: [
                interact.modifiers.restrictRect({
                    restriction: 'parent',
                    endOnly: true
                })
            ],
        })
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', async function() {
            // Load PDF automatically
            const pdfUrl = document.getElementById('document-result').value;
            
            const loadingTask = pdfjsLib.getDocument(pdfUrl);
            loadingTask.promise.then(pdf => {
                // Set pdfDoc to the PDFJS object so we can reference it globally
                pdfDoc = pdf;
                // Get Page number
                pageNum = 1;
                document.getElementById('pageNumber').value = pageNum;
                // Initial page rendering
                renderPage(pageNum);
                
                // Show draggable QR code
                document.getElementsByClassName('draggable')[0].style.display = 'block';
            });
        });
        </script>

</html>