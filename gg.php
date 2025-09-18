<?php

use Simp\Core\extends\wiki\src\Entity\Wiki;
use Simp\Core\extends\wiki\src\enum\WikiStatusEnum;
use Simp\Core\lib\app\App;

require_once __DIR__ . '/vendor/autoload.php';

App::consoleApp();
//
//$wikisData = [
//    [
//        'title' => "PHP Enums are awesome so now what?",
//        'content' => "<p>In PHP (from version 8.1 onward), you can use enums to define a fixed set of possible values for a type. Enums make code more readable and safer compared to using plain constants or strings.</p>",
//        'tags' => ['PHP', 'Enums'],
//        'revisions' => [
//            ['content' => "Added more explanation about backed enums."]
//        ]
//    ],
//    [
//        'title' => "Understanding PHP 8.2 Readonly Classes",
//        'content' => "<p>Readonly classes help prevent modification of object properties after instantiation, increasing immutability.</p>",
//        'tags' => ['PHP', 'Classes'],
//        'revisions' => []
//    ],
//    [
//        'title' => "JavaScript Async/Await Patterns",
//        'content' => "<p>Async/await allows writing asynchronous code in a synchronous manner. It makes promises easier to work with.</p>",
//        'tags' => ['JavaScript', 'Async'],
//        'revisions' => [
//            ['content' => "Added examples of error handling."]
//        ]
//    ],
//    [
//        'title' => "React useEffect Deep Dive",
//        'content' => "<p>useEffect is the hook for side effects in functional components. Proper dependency handling is crucial.</p>",
//        'tags' => ['React', 'Hooks'],
//        'revisions' => []
//    ],
//    [
//        'title' => "Tailwind CSS Responsive Utilities",
//        'content' => "<p>Tailwind CSS allows building responsive UIs using utility-first classes that adapt across breakpoints.</p>",
//        'tags' => ['CSS', 'Tailwind'],
//        'revisions' => []
//    ],
//    [
//        'title' => "PHP 8.1 Fibers Explained",
//        'content' => "<p>Fibers in PHP 8.1 provide a lightweight way to pause and resume code execution, useful for async programming.</p>",
//        'tags' => ['PHP', 'Fibers'],
//        'revisions' => [
//            ['content' => "Added practical usage examples of fibers."]
//        ]
//    ],
//    [
//        'title' => "Understanding JavaScript Closures",
//        'content' => "<p>Closures allow functions to access variables from an outer scope even after that scope has finished executing.</p>",
//        'tags' => ['JavaScript', 'Functions'],
//        'revisions' => []
//    ],
//    [
//        'title' => "React Context API Tips",
//        'content' => "<p>The Context API allows sharing state across components without passing props manually at every level.</p>",
//        'tags' => ['React', 'State'],
//        'revisions' => []
//    ],
//    [
//        'title' => "PHP PDO vs MySQLi",
//        'content' => "<p>PDO offers prepared statements and a unified API for multiple databases, while MySQLi is specific to MySQL.</p>",
//        'tags' => ['PHP', 'Database'],
//        'revisions' => []
//    ],
//    [
//        'title' => "JavaScript Event Loop Explained",
//        'content' => "<p>The event loop handles asynchronous operations in JavaScript, ensuring non-blocking behavior in the browser or Node.js.</p>",
//        'tags' => ['JavaScript', 'Async'],
//        'revisions' => [
//            ['content' => "Added diagram for better visualization."]
//        ]
//    ],
//    [
//        'title' => "React useState vs useReducer",
//        'content' => "<p>useState is suitable for simple state, whereas useReducer is better for complex state management with multiple actions.</p>",
//        'tags' => ['React', 'Hooks'],
//        'revisions' => []
//    ],
//    [
//        'title' => "PHP Attributes (Annotations) Usage",
//        'content' => "<p>Attributes allow metadata to be added to classes, methods, and properties for reflection and framework usage.</p>",
//        'tags' => ['PHP', 'Attributes'],
//        'revisions' => []
//    ],
//    [
//        'title' => "JavaScript Destructuring Assignment",
//        'content' => "<p>Destructuring allows unpacking values from arrays or objects into distinct variables easily.</p>",
//        'tags' => ['JavaScript', 'Syntax'],
//        'revisions' => []
//    ],
//    [
//        'title' => "React Router v6 Basics",
//        'content' => "<p>React Router v6 simplifies routing in React applications with nested routes and hooks.</p>",
//        'tags' => ['React', 'Routing'],
//        'revisions' => []
//    ],
//    [
//        'title' => "Tailwind CSS Dark Mode",
//        'content' => "<p>Tailwind supports dark mode with the `dark:` prefix, enabling theming without extra CSS files.</p>",
//        'tags' => ['CSS', 'Tailwind'],
//        'revisions' => []
//    ],
//    [
//        'title' => "PHP Match Expression",
//        'content' => "<p>Match expressions in PHP 8.0 provide a more concise and safe alternative to switch statements.</p>",
//        'tags' => ['PHP', 'Syntax'],
//        'revisions' => [
//            ['content' => "Added examples comparing switch vs match."]
//        ]
//    ],
//    [
//        'title' => "JavaScript Optional Chaining",
//        'content' => "<p>Optional chaining `?.` allows safe property access without worrying about `null` or `undefined` errors.</p>",
//        'tags' => ['JavaScript', 'Syntax'],
//        'revisions' => []
//    ],
//    [
//        'title' => "React useMemo Optimization",
//        'content' => "<p>useMemo memoizes expensive calculations to avoid unnecessary re-renders.</p>",
//        'tags' => ['React', 'Hooks'],
//        'revisions' => []
//    ],
//    [
//        'title' => "PHP Named Arguments",
//        'content' => "<p>Named arguments allow passing parameters to functions by name instead of position, improving readability.</p>",
//        'tags' => ['PHP', 'Functions'],
//        'revisions' => []
//    ],
//    [
//        'title' => "JavaScript Promises vs Observables",
//        'content' => "<p>Promises handle single async results, while Observables support multiple async events over time.</p>",
//        'tags' => ['JavaScript', 'Async'],
//        'revisions' => [
//            ['content' => "Added example comparing Promises and Observables."]
//        ]
//    ],
//];
//
//foreach ($wikisData as $data) {
//    $wiki = Wiki::create([
//        'title' => $data['title'],
//        'content' => $data['content'],
//        'authors' => [1], // Keep author 1 only
//        'tags' => $data['tags'],
//    ]);
//
//    // Force creation as new
//    $wiki->enforceNew()->save();
//
//    // Add revisions if any
//    foreach ($data['revisions'] as $rev) {
//        $wiki->addRevision($rev['content'], WikiStatusEnum::PUBLISHED, 1);
//    }
//
//    echo "Created wiki: {$data['title']}\n";
//}


/**
 * Second set
 */

$wikisData = [
    [
        'title' => "PHP Traits for Code Reuse",
        'content' => "<p>Traits allow code reuse across multiple classes without using inheritance, helping to avoid duplication.</p>",
        'tags' => ['PHP', 'Traits'],
        'revisions' => [
            ['content' => "Added example of using multiple traits in a class."]
        ]
    ],
    [
        'title' => "JavaScript ES2023 Features",
        'content' => "<p>Learn about the latest JavaScript features like `Array.prototype.findLast` and logical assignment operators.</p>",
        'tags' => ['JavaScript', 'ES2023'],
        'revisions' => []
    ],
    [
        'title' => "React Suspense for Data Fetching",
        'content' => "<p>Suspense allows declarative data fetching in React, pausing rendering until the data is ready.</p>",
        'tags' => ['React', 'Suspense'],
        'revisions' => []
    ],
    [
        'title' => "PHP Type Juggling Explained",
        'content' => "<p>PHP automatically converts between types in certain expressions. Understanding this prevents unexpected bugs.</p>",
        'tags' => ['PHP', 'Types'],
        'revisions' => []
    ],
    [
        'title' => "JavaScript Proxy Objects",
        'content' => "<p>Proxies allow intercepting operations on objects like property access, assignment, or function calls.</p>",
        'tags' => ['JavaScript', 'Objects'],
        'revisions' => [
            ['content' => "Added a practical example of logging property access using Proxy."]
        ]
    ],
    [
        'title' => "React useCallback Hook",
        'content' => "<p>useCallback memoizes functions to avoid unnecessary re-renders of child components.</p>",
        'tags' => ['React', 'Hooks'],
        'revisions' => []
    ],
    [
        'title' => "Tailwind Grid Layout Tips",
        'content' => "<p>Tailwind's grid utilities make it easy to create responsive layouts without custom CSS.</p>",
        'tags' => ['CSS', 'Tailwind'],
        'revisions' => []
    ],
    [
        'title' => "PHP Anonymous Classes",
        'content' => "<p>Anonymous classes are useful for one-off objects, often for testing or simple implementations.</p>",
        'tags' => ['PHP', 'Classes'],
        'revisions' => []
    ],
    [
        'title' => "JavaScript Map vs WeakMap",
        'content' => "<p>Map stores key-value pairs with strong references, while WeakMap holds weak references that can be garbage-collected.</p>",
        'tags' => ['JavaScript', 'Collections'],
        'revisions' => []
    ],
    [
        'title' => "React Error Boundaries",
        'content' => "<p>Error boundaries catch JavaScript errors in a component tree and display fallback UI instead of crashing the app.</p>",
        'tags' => ['React', 'ErrorHandling'],
        'revisions' => []
    ],
    [
        'title' => "PHP Generators for Iteration",
        'content' => "<p>Generators allow creating iterators using `yield`, which is memory efficient for large datasets.</p>",
        'tags' => ['PHP', 'Generators'],
        'revisions' => [
            ['content' => "Added example of a generator with infinite sequence."]
        ]
    ],
    [
        'title' => "JavaScript Nullish Coalescing",
        'content' => "<p>`??` returns the right-hand value only if the left-hand side is `null` or `undefined`, useful for default values.</p>",
        'tags' => ['JavaScript', 'Syntax'],
        'revisions' => []
    ],
    [
        'title' => "React useRef Hook",
        'content' => "<p>useRef keeps a mutable reference to a DOM element or variable that persists across renders.</p>",
        'tags' => ['React', 'Hooks'],
        'revisions' => []
    ],
    [
        'title' => "Tailwind Typography Plugin",
        'content' => "<p>The typography plugin provides pre-styled text classes for headings, paragraphs, and more.</p>",
        'tags' => ['CSS', 'Tailwind'],
        'revisions' => []
    ],
    [
        'title' => "PHP Union Types",
        'content' => "<p>Union types allow a variable or parameter to accept multiple types, improving type safety.</p>",
        'tags' => ['PHP', 'Types'],
        'revisions' => []
    ],
    [
        'title' => "JavaScript Dynamic Imports",
        'content' => "<p>Dynamic imports allow loading modules on demand, enabling code splitting and performance optimization.</p>",
        'tags' => ['JavaScript', 'Modules'],
        'revisions' => []
    ],
    [
        'title' => "React Lazy Loading Components",
        'content' => "<p>React `lazy` function enables code splitting by loading components only when they are rendered.</p>",
        'tags' => ['React', 'Performance'],
        'revisions' => []
    ],
    [
        'title' => "PHP Match Expression with Types",
        'content' => "<p>PHP match expressions now support strict type matching and returning values, simplifying complex switch statements.</p>",
        'tags' => ['PHP', 'Syntax'],
        'revisions' => [
            ['content' => "Added example comparing type-safe match vs switch."]
        ]
    ],
    [
        'title' => "JavaScript Optional Chaining Deep Dive",
        'content' => "<p>Optional chaining allows safe nested property access without throwing errors if a property does not exist.</p>",
        'tags' => ['JavaScript', 'Syntax'],
        'revisions' => []
    ],
    [
        'title' => "React useImperativeHandle Hook",
        'content' => "<p>useImperativeHandle customizes the instance value exposed to parent components when using refs.</p>",
        'tags' => ['React', 'Hooks'],
        'revisions' => []
    ],
];

foreach ($wikisData as $data) {
    $wiki = Wiki::create([
        'title' => $data['title'],
        'content' => $data['content'],
        'authors' => [1], // Keep author 1 only
        'tags' => $data['tags'],
    ]);

    // Force creation as new
    $wiki->enforceNew()->save();

    // Add revisions if any
    foreach ($data['revisions'] as $rev) {
        $wiki->addRevision($rev['content'], WikiStatusEnum::PUBLISHED, 1);
    }

    echo "Created wiki: {$data['title']}\n";
}
