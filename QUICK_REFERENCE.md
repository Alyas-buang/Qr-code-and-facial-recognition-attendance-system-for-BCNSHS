# Frontend Overhaul - Quick Reference Guide
## April 2026

---

## 🚀 Quick Start - Copy & Paste Examples

### 1. Basic Button Animation
```html
<button class="btn btn-primary" onclick="buttonClick(this)">Click Me</button>

<script>
function buttonClick(btn) {
    AnimeUtils.animateButtonLoading(btn, 'Processing...');
    // Simulate API call
    setTimeout(() => {
        AnimeUtils.animateButtonSuccess(btn, 'Done!', 1500);
    }, 2000);
}
</script>
```

### 2. Form with Real-Time Validation
```html
<form id="myForm" class="form-control gap-4">
    <label class="label">
        <span class="label-text">Email</span>
    </label>
    <input type="email" name="email" class="input input-bordered" />
    
    <label class="label">
        <span class="label-text">Password</span>
    </label>
    <input type="password" name="password" class="input input-bordered" />
    
    <button type="submit" class="btn btn-primary">Login</button>
</form>

<script>
const form = document.getElementById('myForm');
const rules = {
    email: { required: true, email: true },
    password: { required: true, minLength: 8 }
};

FormUtils.enableFormValidation(form, rules);

FormUtils.onFormSubmit(form, async () => {
    if (FormUtils.validateForm(form, rules)) {
        // Submit logic here
        console.log('Form is valid!');
    }
});
</script>
```

### 3. Stagger Animate Cards
```html
<div class="grid gap-4 grid-cols-1 md:grid-cols-3">
    <div class="card bg-base-100 shadow-xl"><div class="card-body">Card 1</div></div>
    <div class="card bg-base-100 shadow-xl"><div class="card-body">Card 2</div></div>
    <div class="card bg-base-100 shadow-xl"><div class="card-body">Card 3</div></div>
</div>

<script>
// Automatically done on page load, but you can manually trigger:
AnimeUtils.stagger('.card', 'slideInUp', 100);
</script>
```

### 4. Animated Counter (Statistics)
```html
<div class="stat-card">
    <span class="stat-title">Total Students</span>
    <span class="stat-value" id="studentCount">0</span>
</div>

<script>
// Animate from 0 to 245
AnimeUtils.animateCounter('#studentCount', 0, 245, {
    duration: 2000
});
</script>
```

### 5. Modal with Animations
```html
<label for="modal-1" class="btn btn-primary">Open Modal</label>

<input type="checkbox" id="modal-1" class="modal-toggle" />
<div class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Modal Title</h3>
        <p class="py-4">Modal content here</p>
        <div class="modal-action">
            <label for="modal-1" class="btn">Close</label>
        </div>
    </div>
</div>

<script>
const modalCheckbox = document.getElementById('modal-1');
const modal = document.querySelector('.modal');

modalCheckbox.addEventListener('change', (e) => {
    if (e.target.checked) {
        AnimeUtils.animateModalOpen(modal);
    }
});
</script>
```

### 6. Alert Notifications
```html
<div class="alert alert-success">
    <span>Operation successful!</span>
</div>

<script>
// Auto-animates on page load
// To manually animate:
AnimeUtils.animateAlert('.alert');
</script>
```

### 7. Progress Bar Animation
```html
<progress class="progress progress-primary w-full" value="0" max="100"></progress>

<script>
// Animate to 75%
AnimeUtils.animateProgress('.progress', 75, {
    duration: 1500
});
</script>
```

### 8. File Upload Preview
```html
<form id="uploadForm" class="form-control gap-4">
    <label class="label">
        <span class="label-text">Upload Image</span>
    </label>
    <input type="file" id="fileInput" class="file-input file-input-bordered w-full" />
    <div id="preview" class="mt-4"></div>
</form>

<script>
const fileInput = document.getElementById('fileInput');
const preview = document.getElementById('preview');

FormUtils.enableFilePreview(fileInput, preview, {
    maxSize: 5 * 1024 * 1024, // 5MB
    allowedTypes: ['image/jpeg', 'image/png', 'image/gif']
});
</script>
```

### 9. Custom Animation Timeline
```html
<div class="space-y-4">
    <div class="box bg-blue-500 w-20 h-20 rounded"></div>
    <div class="box bg-green-500 w-20 h-20 rounded"></div>
    <div class="box bg-red-500 w-20 h-20 rounded"></div>
</div>

<script>
const timeline = AnimeUtils.createTimeline();

timeline
    .add({
        targets: '.box:nth-child(1)',
        translateX: 100,
        duration: 500,
        easing: 'easeOutCubic'
    })
    .add({
        targets: '.box:nth-child(2)',
        translateX: 100,
        duration: 500,
        easing: 'easeOutCubic'
    }, '-=250')
    .add({
        targets: '.box:nth-child(3)',
        translateX: 100,
        duration: 500,
        easing: 'easeOutCubic'
    }, '-=250');
</script>
```

### 10. Animation Easing Options
```javascript
// Available easing functions:
'linear'
'easeInQuad', 'easeOutQuad', 'easeInOutQuad'
'easeInCubic', 'easeOutCubic', 'easeInOutCubic'
'easeInQuart', 'easeOutQuart', 'easeInOutQuart'
'easeInQuint', 'easeOutQuint', 'easeInOutQuint'
'easeInExpo', 'easeOutExpo', 'easeInOutExpo'
'easeInCirc', 'easeOutCirc', 'easeInOutCirc'
'easeInBack', 'easeOutBack', 'easeInOutBack'
'easeInElastic', 'easeOutElastic', 'easeInOutElastic'
'easeOutBounce'
'easeOutSpring'

// Usage:
AnimeUtils.animate('.element', {
    opacity: [0, 1],
    duration: 500,
    easing: 'easeOutCubic'
});
```

---

## 📚 Component Snippets

### Stat Cards Row
```html
<div class="grid gap-4 grid-cols-1 md:grid-cols-4">
    <div class="stat-card">
        <span class="stat-title">Total Attendees</span>
        <span class="stat-value">1,234</span>
    </div>
    <div class="stat-card">
        <span class="stat-title">Present Today</span>
        <span class="stat-value">1,150</span>
    </div>
    <div class="stat-card">
        <span class="stat-title">Late</span>
        <span class="stat-value">25</span>
    </div>
    <div class="stat-card">
        <span class="stat-title">Absent</span>
        <span class="stat-value">59</span>
    </div>
</div>
```

### Navigation Buttons
```html
<div class="flex gap-2">
    <button class="btn btn-primary">Primary</button>
    <button class="btn btn-secondary">Secondary</button>
    <button class="btn btn-outline">Outline</button>
    <button class="btn btn-ghost">Ghost</button>
    <button class="btn btn-error">Delete</button>
    <button class="btn btn-success">Confirm</button>
</div>
```

### Form Group
```html
<div class="form-control gap-4">
    <div>
        <label class="label">
            <span class="label-text">Username</span>
        </label>
        <input type="text" class="input input-bordered w-full" />
    </div>
    
    <div>
        <label class="label">
            <span class="label-text">Email</span>
        </label>
        <input type="email" class="input input-bordered w-full" />
    </div>
    
    <button class="btn btn-primary w-full">Submit</button>
</div>
```

### Table with Hover Effects
```html
<div class="overflow-x-auto">
    <table class="table w-full">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <tr class="list-item">
                <td>John Doe</td>
                <td>john@example.com</td>
                <td><span class="badge badge-success">Active</span></td>
                <td>
                    <button class="btn btn-sm btn-ghost">Edit</button>
                </td>
            </tr>
        </tbody>
    </table>
</div>
```

---

## 🎨 Tailwind + DaisyUI Classes Quick Reference

### Colors
```html
<!-- Text -->
<p class="text-primary">Primary Text</p>
<p class="text-success">Success Text</p>
<p class="text-error">Error Text</p>

<!-- Background -->
<div class="bg-base-100">Base 100</div>
<div class="bg-primary text-primary-content">Primary BG</div>

<!-- Borders -->
<div class="border border-primary">Bordered</div>
```

### Spacing
```html
<!-- Padding -->
<div class="p-4">Padding</div>
<div class="px-4">Horizontal Padding</div>
<div class="py-2">Vertical Padding</div>

<!-- Margin -->
<div class="m-4">Margin</div>
<div class="mb-4">Margin Bottom</div>
<div class="mt-2">Margin Top</div>
```

### Sizing
```html
<!-- Width -->
<div class="w-full">Full Width</div>
<div class="w-1/2">Half Width</div>
<div class="w-screen">Screen Width</div>

<!-- Height -->
<div class="h-20">Height 20 (5rem)</div>
<div class="min-h-screen">Min Screen Height</div>
```

### Flexbox
```html
<!-- Direction -->
<div class="flex flex-col">Column</div>
<div class="flex flex-row">Row</div>

<!-- Alignment -->
<div class="flex items-center">Center Items</div>
<div class="flex justify-between">Space Between</div>
<div class="flex gap-4">Gap 4</div>
```

### Grid
```html
<!-- Grid -->
<div class="grid grid-cols-3 gap-4">
    <div>Column 1</div>
    <div>Column 2</div>
    <div>Column 3</div>
</div>

<!-- Responsive -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
    <!-- 1 col mobile, 2 md, 3 lg -->
</div>
```

### Responsive Prefixes
```html
<!-- Mobile First -->
<div class="text-sm md:text-base lg:text-lg">Responsive Text</div>

<!-- Display -->
<div class="hidden md:block">Show on MD and up</div>
```

---

## 🔧 Debug Commands

```javascript
// Check if anime.js is loaded
console.log(AnimeUtils.isReady()); // true or false

// Get all animation presets
console.log(AnimeUtils.getPresets());

// Stop all animations
AnimeUtils.stopAll();

// Check form validation
const form = document.querySelector('#myForm');
console.log(FormUtils.validateForm(form, rules));
```

---

## 📱 Responsive Breakpoints

- **Mobile**: < 640px
- **Tablet (SM)**: ≥ 640px
- **Tablet (MD)**: ≥ 768px  
- **Desktop (LG)**: ≥ 1024px
- **Large Desktop (XL)**: ≥ 1280px
- **2XL**: ≥ 1536px

---

## 🎬 Animation Timing

- **Fast**: 150ms - Quick interactions (focus, hover)
- **Normal**: 300ms - Standard animations (modals, transitions)
- **Slow**: 500ms - Entrance animations (page load)

---

## 📖 File Structure

```
public/assets/
├── css/
│   ├── app.css              ← Tailwind + DaisyUI
│   ├── legacy-theme.css     ← Custom theme
│   └── animations.css       ← NEW: Enhanced animations
└── js/
    ├── anime.min.js         ← NEW: Animation library
    ├── anime-utils.js       ← NEW: Animation utilities
    ├── form-utils.js        ← NEW: Form utilities
    └── face-model-loader.js ← Existing face detection
```

---

## ✨ CSS Classes Added

### Animation Classes
- `.list-item` - Stagger animation for lists
- `.stat-card` - Statistics card styling
- `.loading-spinner` - Spinning loader
- `.loading-pulse` - Pulsing animation
- `.skeleton` - Shimmer loading effect

### States
- `.is-valid` - Valid form field
- `.is-invalid` - Invalid form field
- `.error-message` - Error text styling

---

## 🆘 Common Issues & Fixes

| Issue | Solution |
|-------|----------|
| Animations not working | Check browser console, verify anime.js loaded |
| Form validation not triggering | Call `FormUtils.enableFormValidation(form, rules)` |
| Styles not applied | Ensure CSS files load in order: app.css → legacy-theme.css → animations.css |
| Button animations jerky | Check for conflicting CSS, reduce other animations |
| Mobile animations slow | Reduce animation durations, check device performance |

---

## 📞 Need Help?

1. Check [FRONTEND_OVERHAUL.md](FRONTEND_OVERHAUL.md) for full documentation
2. Review browser console for errors
3. Verify all files are in the correct locations
4. Test in Chrome DevTools to debug

---

**Created: April 2026**
**Last Updated: April 2026**
