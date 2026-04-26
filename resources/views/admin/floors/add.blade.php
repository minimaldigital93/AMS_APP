<!-- Add Floor Modal -->
<div id="addFloorModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 sticky top-0 bg-white z-10">
            <h2 class="text-xl font-bold text-gray-900">Add New Floor</h2>
            <p class="text-sm text-gray-600 mt-1">Create a floor and optionally assign apartment units</p>
        </div>
        <form method="POST" action="{{ route('admin.floors.store') }}" class="p-6 space-y-6">
            @csrf
            
            <!-- Floor Information -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                    </svg>
                    Floor Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Floor Name <span class="text-red-500">*</span></label>
                        <input type="text" name="floor_name" required 
                               placeholder="e.g., 1st Floor, Ground Floor, Floor A"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <input type="text" name="description"
                               placeholder="Optional description of the floor..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <!-- Apartments Section - Creative Design -->
            <div class="bg-gradient-to-br from-emerald-50 via-white to-emerald-50 border border-emerald-200 rounded-xl p-6">
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900 flex items-center gap-3 mb-1">
                        <div class="p-2 bg-gradient-to-br from-emerald-500 to-green-600 rounded-lg">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                            </svg>
                        </div>
                        Assign Apartment Units
                    </h3>
                    <p class="text-sm text-gray-600 ml-11">Add units one by one to build your floor</p>
                </div>
                
                <!-- Input Section with Creative Design -->
                <div class="bg-white rounded-xl border-2 border-emerald-100 p-5 mb-6 shadow-sm">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="relative">
                            <div class="absolute left-0 top-0 w-1 h-8 bg-gradient-to-b from-emerald-500 to-transparent rounded-full"></div>
                            <label class="block text-xs font-semibold text-emerald-700 mb-2 uppercase tracking-wider">🏠 Unit Number</label>
                            <input type="text" id="unitNumber" 
                                   placeholder="e.g., 101"
                                   class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-50 transition text-sm font-medium">
                        </div>
                        <div class="relative">
                            <div class="absolute left-0 top-0 w-1 h-8 bg-gradient-to-b from-blue-500 to-transparent rounded-full"></div>
                            <label class="block text-xs font-semibold text-blue-700 mb-2 uppercase tracking-wider">💰 Monthly Rent</label>
                            <input type="number" id="unitRent" step="0.01" min="0" 
                                   placeholder="0.00"
                                   class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-50 transition text-sm font-medium">
                        </div>
                        <div class="relative">
                            <div class="absolute left-0 top-0 w-1 h-8 bg-gradient-to-b from-purple-500 to-transparent rounded-full"></div>
                            <label class="block text-xs font-semibold text-purple-700 mb-2 uppercase tracking-wider">📊 Status</label>
                            <select id="unitStatus" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-50 transition text-sm font-medium bg-white">
                                <option value="available">🟢 Available</option>
                                <option value="occupied">🔴 Occupied</option>
                                <option value="maintenance">🟡 Maintenance</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" onclick="addUnitOne()" 
                                    class="w-full bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-bold py-2.5 px-4 rounded-lg transition duration-300 flex items-center justify-center gap-2 shadow-md hover:shadow-lg transform hover:scale-105">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Add Unit</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Status Bar -->
                    <div class="mt-4 pt-4 border-t-2 border-gray-100 flex items-center justify-between">
                        <div id="unitCounter" class="text-sm font-semibold">
                            <span class="inline-block bg-gradient-to-r from-emerald-100 to-green-100 text-emerald-700 px-3 py-1 rounded-full">
                                 <span class="font-bold">0 units</span> added
                            </span>
                        </div>
                        <p class="text-xs text-gray-500">💡 Press Enter or click Add Unit button</p>
                    </div>
                </div>

                <!-- Units Display Grid -->
                <div id="apartmentsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Units will be displayed here as they are added -->
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3 pt-4 border-t border-gray-200">
                <button type="submit" onclick="return prepareFormSubmit()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                    Create Floor
                </button>
                <button type="button" onclick="closeAddFloorModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-3 px-4 rounded-lg transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Add Floor Modal Functions
let unitsAdded = [];

// Add CSS animations
const style = document.createElement('style');
style.innerHTML = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .animate-slideIn {
        animation: slideIn 0.3s ease-out forwards;
    }
    
    input:focus, select:focus {
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
`;
document.head.appendChild(style);

function openAddFloorModal() {
    document.getElementById('addFloorModal').classList.remove('hidden');
    // Reset form
    unitsAdded = [];
    document.getElementById('unitNumber').value = '';
    document.getElementById('unitRent').value = '';
    document.getElementById('unitStatus').value = 'available';
    document.getElementById('apartmentsContainer').innerHTML = '';
    document.getElementById('unitCounter').innerHTML = '<span class="font-medium">0 units</span> added';
}

function closeAddFloorModal() {
    document.getElementById('addFloorModal').classList.add('hidden');
}

// Add a single unit
function addUnitOne() {
    const unitNumber = document.getElementById('unitNumber').value.trim();
    const unitRent = document.getElementById('unitRent').value.trim();
    const unitStatus = document.getElementById('unitStatus').value;
    
    // Validation
    if (!unitNumber) {
        alert('Please enter a unit number');
        document.getElementById('unitNumber').focus();
        return;
    }
    
    // Check for duplicates
    if (unitsAdded.some(u => u.number === unitNumber)) {
        alert('Unit ' + unitNumber + ' has already been added');
        document.getElementById('unitNumber').focus();
        return;
    }
    
    // Add unit to array
    const unit = {
        number: unitNumber,
        rent: unitRent || 0,
        status: unitStatus,
        id: Date.now() // Unique ID for removal
    };
    
    unitsAdded.push(unit);
    
    // Clear inputs
    document.getElementById('unitNumber').value = '';
    document.getElementById('unitRent').value = '';
    document.getElementById('unitStatus').value = 'available';
    
    // Refresh display
    displayAddedUnits();
    
    // Focus on unit number for next entry
    document.getElementById('unitNumber').focus();
}

// Display all added units
function displayAddedUnits() {
    const container = document.getElementById('apartmentsContainer');
    
    if (unitsAdded.length === 0) {
        container.innerHTML = '';
        document.getElementById('unitCounter').innerHTML = '<span class="inline-block bg-gradient-to-r from-emerald-100 to-green-100 text-emerald-700 px-3 py-1 rounded-full">📍 <span class="font-bold">0 units</span> added</span>';
        return;
    }
    
    // Update counter with animation
    document.getElementById('unitCounter').innerHTML = `<span class="inline-block bg-gradient-to-r from-emerald-100 to-green-100 text-emerald-700 px-3 py-1 rounded-full animate-pulse">📍 <span class="font-bold">${unitsAdded.length} unit(s)</span> added</span>`;
    
    // Create beautiful unit cards
    container.innerHTML = unitsAdded.map((unit, index) => `
        <div class="unit-card group relative bg-white rounded-xl border-2 border-gray-100 hover:border-emerald-300 p-5 shadow-sm hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 animate-slideIn" style="animation-delay: ${index * 100}ms">
            <!-- Gradient accent -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 via-green-500 to-emerald-500 rounded-t-xl"></div>
            
            <!-- Unit Badge -->
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-100 to-green-100 rounded-lg flex items-center justify-center ring-2 ring-emerald-200">
                        <span class="text-lg font-black text-emerald-600">${unit.number.substring(0, 1)}</span>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-gray-900">Unit ${unit.number}</h4>
                        <p class="text-xs text-gray-500">Added to floor</p>
                    </div>
                </div>
                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button type="button" onclick="removeUnit(${unit.id})" 
                            class="bg-red-50 hover:bg-red-100 text-red-600 hover:text-red-700 p-2 rounded-lg transition transform hover:scale-110 duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Unit Details Grid -->
            <div class="space-y-3">
                <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-blue-50 to-blue-50 rounded-lg border border-blue-100">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.5 5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm6.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM9 10a1 1 0 11-2 0 1 1 0 012 0zm6 0a1 1 0 11-2 0 1 1 0 012 0zM9 15a1 1 0 11-2 0 1 1 0 012 0zm6 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                    </svg>
                    <div class="flex-1">
                        <p class="text-xs font-semibold text-gray-600 uppercase">Rent</p>
                        <p class="text-lg font-bold text-blue-700">${unit.rent ? '$' + parseFloat(unit.rent).toFixed(2) : '$0.00'}</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="flex-1 flex items-center gap-3 p-3 bg-gradient-to-r from-purple-50 to-purple-50 rounded-lg border border-purple-100">
                        <svg class="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 3.062v6.050A3.066 3.066 0 0117 15.391v1.953a1.033 1.033 0 11-2.066 0v-.934a3.066 3.066 0 01-3.074 0 3.066 3.066 0 01-3.074 0 3.066 3.066 0 01-3.074 0V17.422a1.033 1.033 0 11-2.066 0v-1.953A3.066 3.066 0 016.267 6.517v-6.06zm9.466 5.882a1.033 1.033 0 00-1.457-1.457L10 8.543 7.724 6.267a1.033 1.033 0 11-1.457 1.457l2.276 2.276-2.276 2.276a1.033 1.033 0 001.457 1.457L10 11.457l2.276 2.276a1.033 1.033 0 001.457-1.457l-2.276-2.276 2.276-2.276z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <p class="text-xs font-semibold text-gray-600 uppercase">Status</p>
                            <p class="text-sm font-bold text-purple-700 capitalize">${unit.status}</p>
                        </div>
                    </div>
                    <div class="px-3 py-2 bg-gradient-to-r from-emerald-100 to-green-100 text-emerald-700 rounded-lg border border-emerald-200 text-center">
                        <p class="text-xs font-bold uppercase">✓ Added</p>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="apartments[${index}][apartment_number]" value="${unit.number}">
            <input type="hidden" name="apartments[${index}][monthly_rent]" value="${unit.rent}">
            <input type="hidden" name="apartments[${index}][status]" value="${unit.status}">
        </div>
    `).join('');
}

// Remove a unit
function removeUnit(id) {
    unitsAdded = unitsAdded.filter(u => u.id !== id);
    displayAddedUnits();
}

// Prepare form submission
function prepareFormSubmit() {
    const container = document.getElementById('apartmentsContainer');
    
    // Clear existing hidden inputs
    container.innerHTML = '';
    
    if (unitsAdded.length === 0) {
        return true; // Allow form to submit with just the floor
    }
    
    // Create hidden inputs for each unit
    unitsAdded.forEach((unit, index) => {
        const div = document.createElement('div');
        div.innerHTML = `
            <input type="hidden" name="apartments[${index}][apartment_number]" value="${unit.number}">
            <input type="hidden" name="apartments[${index}][monthly_rent]" value="${unit.rent}">
            <input type="hidden" name="apartments[${index}][status]" value="${unit.status}">
        `;
        container.appendChild(div);
    });
    
    console.log(`Prepared ${unitsAdded.length} apartments for submission`);
    return true; // Allow form to submit
}

// Allow Enter key to add unit
document.addEventListener('DOMContentLoaded', function() {
    const unitNumberField = document.getElementById('unitNumber');
    const unitRentField = document.getElementById('unitRent');
    
    if (unitNumberField) {
        unitNumberField.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addUnitOne();
            }
        });
    }
    
    if (unitRentField) {
        unitRentField.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addUnitOne();
            }
        });
    }
});
</script>
