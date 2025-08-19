<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['order_user'])) {
    header('Location: /index.php');
    exit;
}

// Get user information from session
$user_name = $_SESSION['order_user'];
$full_name = $_SESSION['order_full_name'];
$user_role = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervace stolů - Timeline View</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .nav-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .nav-link {
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .main-content {
            display: flex;
            height: calc(100vh - 200px);
            min-height: 600px;
        }

        /* Left Panel - Form */
        .left-panel {
            width: 350px;
            padding: 30px;
            border-right: 1px solid #e9ecef;
            background: #f8f9fa;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .required { color: #e74c3c; }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            width: 100%;
            margin-bottom: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-success { background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); }
        .btn-warning { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        .btn-danger { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        .btn-secondary { background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); }

        /* Right Panel - Timeline */
        .right-panel {
            flex: 1;
            padding: 20px;
            overflow: auto;
        }

        .timeline-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .date-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timeline-container {
            position: relative;
            overflow-x: auto;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            min-height: 500px;
        }

        .timeline {
            display: grid;
            grid-template-columns: 80px repeat(10, 120px);
            min-width: 1280px;
            position: relative;
        }

        .time-header {
            background: #f8f9fa;
            border-bottom: 2px solid #ddd;
            font-weight: 600;
            padding: 15px 5px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .table-header {
            background: #e9ecef;
            border-bottom: 2px solid #ddd;
            font-weight: 600;
            padding: 15px 5px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .time-slot {
            height: 60px;
            border-right: 1px solid #eee;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 500;
            background: #fafafa;
        }

        .table-slot {
            height: 60px;
            border-right: 1px solid #eee;
            border-bottom: 1px solid #eee;
            position: relative;
            cursor: pointer;
        }

        .table-slot:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .reservation-block {
            position: absolute;
            left: 2px;
            right: 2px;
            top: 2px;
            bottom: 2px;
            border-radius: 4px;
            padding: 4px;
            font-size: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s ease;
            z-index: 10;
        }

        .reservation-block:hover {
            transform: scale(1.02);
            z-index: 20;
        }

        .reservation-block.pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            color: #856404;
        }

        .reservation-block.confirmed {
            background: linear-gradient(135deg, #d4edda 0%, #81ecec 100%);
            border: 1px solid #28a745;
            color: #155724;
        }

        .reservation-block.seated {
            background: linear-gradient(135deg, #d1ecf1 0%, #74b9ff 100%);
            border: 1px solid #17a2b8;
            color: #0c5460;
        }

        .reservation-block.finished {
            background: linear-gradient(135deg, #e2e3e5 0%, #b2bec3 100%);
            border: 1px solid #6c757d;
            color: #495057;
        }

        .reservation-block.cancelled {
            background: linear-gradient(135deg, #f8d7da 0%, #fab1a0 100%);
            border: 1px solid #dc3545;
            color: #721c24;
            text-decoration: line-through;
        }

        .reservation-block.no_show {
            background: linear-gradient(135deg, #f8d7da 0%, #fdcb6e 100%);
            border: 1px solid #fd79a8;
            color: #721c24;
            opacity: 0.7;
        }

        .reservation-name {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .reservation-details {
            font-size: 9px;
            opacity: 0.8;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover { color: #333; }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .modal-actions .btn {
            width: auto;
            margin-bottom: 0;
            flex: 1;
            min-width: 100px;
        }

        @media (max-width: 1200px) {
            .main-content { flex-direction: column; height: auto; }
            .left-panel { width: 100%; border-right: none; border-bottom: 1px solid #e9ecef; }
            .timeline { grid-template-columns: 60px repeat(8, 100px); min-width: 860px; }
        }

        @media (max-width: 768px) {
            .container { margin: 10px; }
            .timeline-controls { flex-direction: column; align-items: stretch; }
            .timeline { grid-template-columns: 50px repeat(6, 80px); min-width: 530px; }
            .left-panel { padding: 20px; }
        }

        /* Reservation Statistics Styles */
        .stats-section {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .stats-pills {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .stats-pill {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            min-width: 80px;
            justify-content: center;
        }

        .slots-toggle {
            background: #6c757d;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .slots-toggle:hover {
            background: #545b62;
        }

        .slots-toggle.active {
            background: #28a745;
        }

        .slots-list {
            display: none;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background: white;
            margin-top: 8px;
        }

        .slots-list.show {
            display: block;
        }

        .slot-item {
            padding: 6px 12px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }

        .slot-item:last-child {
            border-bottom: none;
        }

        .slot-item.high-occupancy {
            background: rgba(255, 193, 7, 0.2);
            border-left: 3px solid #ffc107;
        }

        .slot-time {
            font-weight: 600;
            color: #495057;
        }

        .slot-persons {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
            color: #495057;
        }

        .slot-item.high-occupancy .slot-persons {
            background: #ffc107;
            color: #212529;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🍽️ Rezervace stolů - Timeline</h1>
            <p>Moderní systém pro správu rezervací s časovou osou</p>
            <div class="nav-links">
                <a href="../index.php" class="nav-link">Zpět na hlavní stránku</a>
                <a href="reservations_legacy.php" class="nav-link">Starý systém rezervací</a>
            </div>
        </div>
        
        <div class="main-content">
            <!-- Left Panel - Form -->
            <div class="left-panel">
                <h3 style="margin-bottom: 20px; color: #667eea;">📝 Nová rezervace</h3>
                <div id="form-alert-container"></div>
                
                <form id="reservation-form">
                    <div class="form-group">
                        <label>Jméno zákazníka <span class="required">*</span></label>
                        <input type="text" id="customer_name" required>
                    </div>

                    <div class="form-group">
                        <label>Telefonní číslo <span class="required">*</span></label>
                        <input type="tel" id="phone" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="email">
                    </div>

                    <div class="form-group">
                        <label>Počet osob <span class="required">*</span></label>
                        <select id="party_size" required>
                            <option value="">Vyberte počet</option>
                            <option value="1">1 osoba</option>
                            <option value="2">2 osoby</option>
                            <option value="3">3 osoby</option>
                            <option value="4">4 osoby</option>
                            <option value="5">5 osob</option>
                            <option value="6">6 osob</option>
                            <option value="8">8 osob</option>
                            <option value="10">10 osob</option>
                            <option value="12">12 osob</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Stůl</label>
                        <select id="table_number">
                            <option value="">Automatické přiřazení</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Čas <span class="required">*</span></label>
                        <select id="reservation_time" required>
                            <option value="">Vyberte čas</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select id="status">
                            <option value="pending">Čekající potvrzení</option>
                            <option value="confirmed">Potvrzeno</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Poznámka</label>
                        <textarea id="notes" rows="3" placeholder="Speciální požadavky, alergie..."></textarea>
                    </div>

                    <button type="submit" class="btn">💾 Vytvořit rezervaci</button>
                </form>
            </div>

            <!-- Right Panel - Timeline -->
            <div class="right-panel">
                <div class="timeline-controls">
                    <div class="date-control">
                        <label>📅 Datum:</label>
                        <input type="date" id="timeline-date">
                        <button class="btn" onclick="loadTimeline()" style="width: auto; margin-bottom: 0; padding: 8px 16px;">🔄 Načíst</button>
                    </div>
                    
                    <!-- Opening Hours Form -->
                    <div class="opening-hours-form" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <label style="margin: 0; font-weight: 600; color: #495057;">🕐 Otevírací doba:</label>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <input type="time" id="open_time" style="padding: 5px 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;" step="1800">
                                <span>-</span>
                                <input type="time" id="close_time" style="padding: 5px 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;" step="1800">
                            </div>
                            <button type="button" onclick="saveOpeningHours()" style="padding: 5px 12px; background: #28a745; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer;">💾 Uložit</button>
                        </div>
                        <div id="opening-hours-status" style="margin-top: 8px; font-size: 12px;"></div>
                    </div>
                </div>

                <!-- Reservation Statistics Section -->
                <div class="stats-section">
                    <div class="stats-pills">
                        <div class="stats-pill">
                            📊 Rezervace: <span id="stats-reservations">-</span>
                        </div>
                        <div class="stats-pill">
                            👥 Osob: <span id="stats-persons">-</span>
                        </div>
                        <button class="slots-toggle" id="slots-toggle" onclick="toggleSlotsList()">
                            📅 Obsazenost slotů
                        </button>
                    </div>
                    
                    <div class="slots-list" id="slots-list">
                        <!-- Slot occupancy list will be populated by JavaScript -->
                    </div>
                </div>

                <div id="timeline-alert-container"></div>

                <div class="timeline-container">
                    <div class="timeline" id="timeline">
                        <!-- Timeline se vygeneruje pomocí JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pro detail rezervace -->
    <div id="reservationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Detail rezervace</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modal-alert-container"></div>
            <div id="modal-content">
                <!-- Dynamický obsah -->
            </div>
            <div class="modal-actions" id="modal-actions">
                <!-- Dynamické akce -->
            </div>
        </div>
    </div>

    <script>
        // Globální proměnné
        const openingHour = 10;
        const closingHour = 23;
        const timeSlots = [];
        let tables = [];
        let currentReservations = [];
        let currentDate = new Date().toISOString().split('T')[0];
        let currentOpeningHours = { open_time: '10:00', close_time: '23:00' };

        // Inicializace při načtení stránky
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
        });

        function initializePage() {
            setDefaultDate();
            loadData(); // Load everything in proper sequence
            
            // Event listener pro form
            document.getElementById('reservation-form').addEventListener('submit', handleFormSubmit);
            
            // Event listener for date change
            document.getElementById('timeline-date').addEventListener('change', function() {
                currentDate = this.value;
                loadData();
            });
        }
        
        // Load all data in proper sequence: tables -> opening hours -> timeline -> reservations
        async function loadData() {
            try {
                await loadTables();
                await loadOpeningHours();
                generateTimeline();
                await loadReservations();
            } catch (error) {
                console.error('Error loading data:', error);
                showAlert('Chyba při načítání dat', 'error', 'timeline-alert-container');
            }
        }

        function setDefaultDate() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('timeline-date').value = today;
            currentDate = today;
        }

        function generateTimeSlots(openTime = null, closeTime = null) {
            const timeSelect = document.getElementById('reservation_time');
            timeSelect.innerHTML = '<option value="">Vyberte čas</option>';
            
            // Use provided opening hours or defaults
            const startHour = openTime ? parseInt(openTime.split(':')[0]) : openingHour;
            const startMinute = openTime ? parseInt(openTime.split(':')[1]) : 0;
            const endHour = closeTime ? parseInt(closeTime.split(':')[0]) : closingHour;
            const endMinute = closeTime ? parseInt(closeTime.split(':')[1]) : 0;
            
            // Clear timeSlots array for timeline
            timeSlots.length = 0;
            
            for (let hour = openingHour; hour <= closingHour; hour++) {
                for (let minute = 0; minute < 60; minute += 30) {
                    if (hour === closingHour && minute > 0) break;
                    
                    const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                    
                    // Add to timeSlots for timeline (all 30-minute slots)
                    timeSlots.push(timeString);
                }
            }
            
            // Generate reservation time options (30-minute intervals within opening hours)
            for (let hour = startHour; hour <= endHour; hour++) {
                for (let minute = 0; minute < 60; minute += 30) {
                    // Skip if before opening time
                    if (hour === startHour && minute < startMinute) continue;
                    
                    // Skip if too late (reservation needs 2 hours, so last slot is 2h before closing)
                    if (hour > endHour - 2) break;
                    if (hour === endHour - 2 && minute > endMinute) break;
                    
                    // Skip if after closing time  
                    if (hour === endHour && minute >= endMinute) break;
                    
                    const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                    const option = document.createElement('option');
                    option.value = timeString;
                    option.textContent = timeString;
                    timeSelect.appendChild(option);
                }
            }
        }

        async function loadTables() {
            try {
                // Load real tables from existing endpoint
                const response = await fetch(`/pizza/api/restaurant-api.php?action=tables-with-reservations&date=${currentDate}`);
                const data = await response.json();
                
                if (data.ok) {
                    tables = data.data.map(table => ({
                        table_number: table.table_number,
                        table_code: table.table_code || `Stůl ${table.table_number}`,
                        status: table.status || 'free'
                    }));
                    populateTableSelect();
                } else {
                    console.error('Failed to load tables:', data.error);
                    // Fallback to mock data
                    tables = [];
                    for (let i = 1; i <= 10; i++) {
                        tables.push({
                            table_number: i,
                            table_code: `Stůl ${i}`,
                            status: 'free'
                        });
                    }
                    populateTableSelect();
                }
            } catch (error) {
                console.error('Chyba při načítání stolů:', error);
                // Fallback to mock data in case of error
                tables = [];
                for (let i = 1; i <= 10; i++) {
                    tables.push({
                        table_number: i,
                        table_code: `Stůl ${i}`,
                        status: 'free'
                    });
                }
                populateTableSelect();
            }
        }

        function populateTableSelect() {
            const tableSelect = document.getElementById('table_number');
            tableSelect.innerHTML = '<option value="">Automatické přiřazení</option>';
            
            tables.forEach(table => {
                const option = document.createElement('option');
                option.value = table.table_number;
                option.textContent = table.table_code || `Stůl ${table.table_number}`;
                tableSelect.appendChild(option);
            });
        }

        function generateTimeline() {
            const timeline = document.getElementById('timeline');
            timeline.innerHTML = '';
            
            // Use current opening hours to determine timeline bounds
            const openHour = parseInt(currentOpeningHours.open_time.split(':')[0]);
            const openMin = parseInt(currentOpeningHours.open_time.split(':')[1]);
            const closeHour = parseInt(currentOpeningHours.close_time.split(':')[0]);
            const closeMin = parseInt(currentOpeningHours.close_time.split(':')[1]);
            
            // Header řádek
            const timeHeader = document.createElement('div');
            timeHeader.className = 'time-header';
            timeHeader.textContent = 'Čas';
            timeline.appendChild(timeHeader);
            
            // Stoly v header
            tables.forEach(table => {
                const tableHeader = document.createElement('div');
                tableHeader.className = 'table-header';
                tableHeader.textContent = table.table_code || `Stůl ${table.table_number}`;
                timeline.appendChild(tableHeader);
            });
            
            // Časové sloty (každých 30 minut) - pouze v otevírací době
            for (let hour = openHour; hour <= closeHour; hour++) {
                for (let minute = 0; minute < 60; minute += 30) {
                    // Skip slots before opening time
                    if (hour === openHour && minute < openMin) continue;
                    
                    // Skip slots after closing time
                    if (hour === closeHour && minute >= closeMin) break;
                    if (hour > closeHour) break;
                    
                    const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                    
                    // Čas ve sloukci
                    const timeSlot = document.createElement('div');
                    timeSlot.className = 'time-slot';
                    timeSlot.textContent = timeString;
                    timeline.appendChild(timeSlot);
                    
                    // Table slots
                    tables.forEach(table => {
                        const tableSlot = document.createElement('div');
                        tableSlot.className = 'table-slot';
                        tableSlot.dataset.time = timeString;
                        tableSlot.dataset.table = table.table_number;
                        timeline.appendChild(tableSlot);
                    });
                }
            }
        }
        
        async function loadOpeningHours() {
            try {
                const response = await fetch(`/api/reservations/opening_hours.php?date=${currentDate}`);
                const data = await response.json();
                
                if (data.ok) {
                    currentOpeningHours = {
                        open_time: data.open_time,
                        close_time: data.close_time
                    };
                    
                    // Update form fields
                    document.getElementById('open_time').value = data.open_time;
                    document.getElementById('close_time').value = data.close_time;
                    
                    // Regenerate time slots for reservation form
                    generateTimeSlots(data.open_time, data.close_time);
                } else {
                    console.error('Failed to load opening hours:', data.error);
                    showAlert('Upozornění: Načtení otevírací doby selhalo, používají se výchozí hodnoty.', 'warning', 'timeline-alert-container');
                    
                    // Use defaults
                    currentOpeningHours = { open_time: '10:00', close_time: '23:00' };
                    document.getElementById('open_time').value = '10:00';
                    document.getElementById('close_time').value = '23:00';
                    generateTimeSlots('10:00', '23:00');
                }
            } catch (error) {
                console.error('Error loading opening hours:', error);
                showAlert('Upozornění: Načtení otevírací doby selhalo, používají se výchozí hodnoty.', 'warning', 'timeline-alert-container');
                
                // Use defaults
                currentOpeningHours = { open_time: '10:00', close_time: '23:00' };
                document.getElementById('open_time').value = '10:00';
                document.getElementById('close_time').value = '23:00';
                generateTimeSlots('10:00', '23:00');
            }
        }
        
        async function saveOpeningHours() {
            const openTime = document.getElementById('open_time').value;
            const closeTime = document.getElementById('close_time').value;
            
            if (!openTime || !closeTime) {
                showAlert('Zadejte otevírací i zavírací čas', 'error', 'opening-hours-status');
                return;
            }
            
            if (openTime >= closeTime) {
                showAlert('Otevírací čas musí být dříve než zavírací', 'error', 'opening-hours-status');
                return;
            }
            
            try {
                const response = await fetch('/api/reservations/opening_hours.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        date: currentDate,
                        open_time: openTime,
                        close_time: closeTime
                    })
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    showAlert('Otevírací doba byla uložena', 'success', 'opening-hours-status');
                    currentOpeningHours = { open_time: openTime, close_time: closeTime };
                    
                    // Regenerate timeline and time slots
                    generateTimeSlots(openTime, closeTime);
                    generateTimeline();
                    await loadReservations();
                } else {
                    showAlert('Chyba při ukládání: ' + data.error, 'error', 'opening-hours-status');
                }
            } catch (error) {
                console.error('Error saving opening hours:', error);
                showAlert('Chyba při ukládání otevírací doby', 'error', 'opening-hours-status');
            }
        }

        async function loadReservations() {
            try {
                const response = await fetch(`/api/reservations/list.php?date=${currentDate}`);
                const data = await response.json();
                
                if (data.ok) {
                    currentReservations = data.data;
                    renderReservations();
                    // Load stats after reservations are loaded
                    await loadReservationStats();
                } else {
                    showAlert('Chyba při načítání rezervací: ' + data.error, 'error', 'timeline-alert-container');
                }
            } catch (error) {
                console.error('Chyba při načítání rezervací:', error);
                showAlert('Chyba při načítání rezervací', 'error', 'timeline-alert-container');
            }
        }
        
        // Keep the original loadTimeline function for the reload button
        async function loadTimeline() {
            currentDate = document.getElementById('timeline-date').value;
            await loadData();
        }

        // Reservation Statistics Functions
        async function loadReservationStats() {
            try {
                // Use current opening hours if available, otherwise defaults
                const openTime = currentOpeningHours?.open_time || '16:00';
                const closeTime = currentOpeningHours?.close_time || '22:00';
                
                const response = await fetch(`/pizza/api/reservations-stats.php?date=${currentDate}&open_time=${openTime}&close_time=${closeTime}`);
                const data = await response.json();
                
                if (data.ok) {
                    updateStatsDisplay(data);
                } else {
                    console.error('Error loading stats:', data.error);
                    resetStatsDisplay();
                }
            } catch (error) {
                console.error('Error loading reservation stats:', error);
                resetStatsDisplay();
            }
        }

        function updateStatsDisplay(statsData) {
            // Update pills
            document.getElementById('stats-reservations').textContent = statsData.reservation_count;
            document.getElementById('stats-persons').textContent = statsData.total_persons;
            
            // Update slots list
            const slotsList = document.getElementById('slots-list');
            slotsList.innerHTML = '';
            
            if (statsData.slots && statsData.slots.length > 0) {
                statsData.slots.forEach(slot => {
                    const slotItem = document.createElement('div');
                    slotItem.className = 'slot-item';
                    
                    // Optional highlighting for high occupancy (threshold = 30)
                    const PERSON_THRESHOLD = 30;
                    if (slot.persons >= PERSON_THRESHOLD) {
                        slotItem.classList.add('high-occupancy');
                    }
                    
                    slotItem.innerHTML = `
                        <span class="slot-time">${slot.time}</span>
                        <span class="slot-persons">${slot.persons} osob</span>
                    `;
                    
                    slotsList.appendChild(slotItem);
                });
            } else {
                slotsList.innerHTML = '<div class="slot-item">Žádná data pro vybrané datum</div>';
            }
        }

        function resetStatsDisplay() {
            // Reset to zeros on error
            document.getElementById('stats-reservations').textContent = '0';
            document.getElementById('stats-persons').textContent = '0';
            
            const slotsList = document.getElementById('slots-list');
            slotsList.innerHTML = '<div class="slot-item">Chyba při načítání dat</div>';
        }

        function toggleSlotsList() {
            const slotsList = document.getElementById('slots-list');
            const toggleButton = document.getElementById('slots-toggle');
            
            slotsList.classList.toggle('show');
            toggleButton.classList.toggle('active');
            
            if (slotsList.classList.contains('show')) {
                toggleButton.textContent = '📅 Skrýt obsazenost';
            } else {
                toggleButton.textContent = '📅 Obsazenost slotů';
            }
        }

        function renderReservations() {
            // Vyčisti existující rezervace
            document.querySelectorAll('.reservation-block').forEach(block => block.remove());
            
            currentReservations.forEach(reservation => {
                if (!reservation.table_number) return;
                
                const startTime = reservation.reservation_time.substring(0, 5);
                const tableNumber = reservation.table_number;
                
                // Najdi odpovídající slot
                const slot = document.querySelector(`[data-time="${startTime}"][data-table="${tableNumber}"]`);
                if (!slot) return;
                
                // Vytvoř reservation block
                const block = document.createElement('div');
                block.className = `reservation-block ${reservation.status}`;
                block.innerHTML = `
                    <div class="reservation-name">${escapeHtml(reservation.customer_name)}</div>
                    <div class="reservation-details">${reservation.party_size} osob</div>
                    <div class="reservation-details">${startTime}</div>
                `;
                
                block.addEventListener('click', () => showReservationModal(reservation));
                
                // Výška bloku - 2 hodiny = 4 sloty (každých 30 min)
                const blockHeight = 4 * 60; // 4 sloty * 60px
                block.style.height = `${blockHeight - 4}px`; // -4px pro mezery
                
                slot.appendChild(block);
            });
        }

        function showReservationModal(reservation) {
            const modal = document.getElementById('reservationModal');
            const modalContent = document.getElementById('modal-content');
            const modalActions = document.getElementById('modal-actions');
            
            modalContent.innerHTML = `
                <p><strong>Zákazník:</strong> ${escapeHtml(reservation.customer_name)}</p>
                <p><strong>Telefon:</strong> ${escapeHtml(reservation.phone)}</p>
                ${reservation.email ? `<p><strong>Email:</strong> ${escapeHtml(reservation.email)}</p>` : ''}
                <p><strong>Počet osob:</strong> ${reservation.party_size}</p>
                <p><strong>Datum:</strong> ${reservation.reservation_date}</p>
                <p><strong>Čas:</strong> ${reservation.reservation_time}</p>
                <p><strong>Stůl:</strong> ${reservation.table_number ? `Stůl ${reservation.table_number}` : 'Nepřiřazen'}</p>
                <p><strong>Stav:</strong> <span class="status ${reservation.status}">${getStatusText(reservation.status)}</span></p>
                ${reservation.notes ? `<p><strong>Poznámka:</strong> ${escapeHtml(reservation.notes)}</p>` : ''}
            `;
            
            // Generuj akce podle stavu
            modalActions.innerHTML = getModalActions(reservation);
            
            modal.style.display = 'block';
        }

        function getModalActions(reservation) {
            let actions = '';
            
            if (reservation.status === 'pending' || reservation.status === 'confirmed') {
                actions += `<button class="btn btn-success" onclick="seatReservation(${reservation.id})">🪑 Posadit</button>`;
            }
            
            if (reservation.status === 'seated') {
                actions += `<button class="btn btn-secondary" onclick="finishReservation(${reservation.id})">✅ Dokončit</button>`;
            }
            
            if (!['finished', 'cancelled', 'no_show'].includes(reservation.status)) {
                actions += `<button class="btn btn-danger" onclick="cancelReservation(${reservation.id})">❌ Zrušit</button>`;
            }
            
            return actions;
        }

        function closeModal() {
            document.getElementById('reservationModal').style.display = 'none';
            clearAlert('modal-alert-container');
        }

        async function seatReservation(id) {
            await performReservationAction('/api/reservations/seat.php', { id: id }, 'Posazení');
        }

        async function finishReservation(id) {
            await performReservationAction('/api/reservations/finish.php', { id: id }, 'Dokončení');
        }

        async function cancelReservation(id) {
            if (confirm('Opravdu chcete zrušit tuto rezervaci?')) {
                await performReservationAction('/api/reservations/cancel.php', { id: id }, 'Zrušení');
            }
        }

        async function performReservationAction(url, data, actionName) {
            try {
                const formData = new FormData();
                Object.keys(data).forEach(key => formData.append(key, data[key]));
                
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.ok) {
                    showAlert(result.message || `${actionName} proběhlo úspěšně`, 'success', 'modal-alert-container');
                    setTimeout(() => {
                        closeModal();
                        loadTimeline();
                    }, 1500);
                } else {
                    showAlert(`Chyba při ${actionName.toLowerCase()}: ` + result.error, 'error', 'modal-alert-container');
                }
            } catch (error) {
                showAlert(`Chyba při ${actionName.toLowerCase()}: ` + error.message, 'error', 'modal-alert-container');
            }
        }

        async function handleFormSubmit(e) {
            e.preventDefault();
            clearAlert('form-alert-container');
            
            const formData = {
                customer_name: document.getElementById('customer_name').value,
                phone: document.getElementById('phone').value,
                email: document.getElementById('email').value,
                party_size: parseInt(document.getElementById('party_size').value),
                reservation_date: currentDate,
                reservation_time: document.getElementById('reservation_time').value,
                table_number: document.getElementById('table_number').value || null,
                status: document.getElementById('status').value,
                notes: document.getElementById('notes').value
            };

            try {
                const response = await fetch('/api/reservations/create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();
                
                if (result.ok) {
                    showAlert('✅ Rezervace byla úspěšně vytvořena!', 'success', 'form-alert-container');
                    document.getElementById('reservation-form').reset();
                    document.getElementById('status').value = 'pending'; // Reset to default
                    loadTimeline(); // Reload timeline
                } else {
                    showAlert('❌ ' + result.error, 'error', 'form-alert-container');
                }
            } catch (error) {
                showAlert('❌ Chyba při vytváření rezervace: ' + error.message, 'error', 'form-alert-container');
            }
        }

        function getStatusText(status) {
            const statusMap = {
                'pending': 'Čeká na potvrzení',
                'confirmed': 'Potvrzeno',
                'seated': 'Posazeni',
                'finished': 'Dokončeno',
                'cancelled': 'Zrušeno',
                'no_show': 'Nedorazil'
            };
            return statusMap[status] || status;
        }

        function showAlert(message, type, containerId) {
            const container = document.getElementById(containerId);
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            
            setTimeout(() => {
                clearAlert(containerId);
            }, 5000);
        }

        function clearAlert(containerId) {
            document.getElementById(containerId).innerHTML = '';
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('reservationModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>