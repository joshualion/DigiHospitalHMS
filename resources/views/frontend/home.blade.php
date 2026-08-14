<x-frontend-layout title="Home" class="overflow-x-hidden">

<!-- 1. Hero Slider -->
<section x-data="heroSlider()"
         x-init="startAutoplay()"
         @mouseenter="pauseAutoplay()"
         @mouseleave="startAutoplay()"
         class="relative top-0 z-100
           border-b border-gray-200 dark:border-gray-700
           bg-white/80 dark:bg-gray-900/80
           backdrop-blur-md shadow-lg shadow-primary/10
           text-gray-800 dark:text-white w-full"
         style="height: 70vh; margin-top: -48px;">

    <template x-for="(slide, index) in slides" :key="index">
        <div x-show="current === index"
             x-transition:enter="transition ease-out duration-700"
             x-transition:enter-start="opacity-0 transform translate-x-full"
             x-transition:enter-end="opacity-100 transform translate-x-0"
             x-transition:leave="transition ease-in duration-700"
             x-transition:leave-start="opacity-100 transform translate-x-0"
             x-transition:leave-end="opacity-0 transform -translate-x-full"
             class="absolute inset-0 w-full h-full">
            <img :src="slide.image" class="w-full h-full object-cover block" alt="" />
            <div class="absolute inset-0 bg-black/30 dark:bg-black/60"></div>
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center text-white px-4">
                <h1 class="text-5xl font-bold mb-4" x-text="slide.title"></h1>
                <p class="text-xl mb-6" x-text="slide.subtitle"></p>
                <a href="#services"
                   class="bg-primary dark:bg-primary-dark hover:bg-primary-dark dark:hover:bg-primary px-6 py-3 rounded text-lg font-semibold">Explore Services</a>
            </div>
        </div>
    </template>

    <!-- Indicators -->
    <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
        <template x-for="(slide, index) in slides" :key="index">
            <span @click="goTo(index)"
                  :class="current === index ? 'bg-primary dark:bg-primary-dark' : 'bg-white/50 dark:bg-gray-400/50'"
                  class="w-3 h-3 rounded-full cursor-pointer"></span>
        </template>
    </div>
</section>

<!-- 1.1. Mini Band (Blade Lucide components) -->
<section class="relative z-20 -mt-8">
  <div class="bg-primary text-white shadow-lg rounded-xl max-w-6xl mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-white/20">

      <!-- Emergency Contact -->
      <div class="flex flex-col items-center justify-center p-6 text-center">
        <div class="flex items-center gap-3 mb-2">
          <span class="p-2 rounded-full bg-white/10">
            <x-lucide-phone-call class="w-6 h-6 text-white" aria-hidden="true" />
          </span>
          <span class="text-lg font-semibold">Emergency Contact</span>
        </div>
        <a href="tel:+2348091574444" class="mt-1 text-2xl font-bold hover:underline">+234 809 157 4444</a>
      </div>

      <!-- Doctor Appointment -->
      <div class="flex flex-col items-center justify-center p-6 text-center">
        <div class="flex items-center gap-3 mb-2">
          <span class="p-2 rounded-full bg-white/10">
            <x-lucide-calendar-check class="w-6 h-6 text-white" aria-hidden="true" />
          </span>
          <span class="text-lg font-semibold">Doctor Appointment</span>
        </div>
        <a href="{{ route('appointment') }}" class="mt-2 inline-flex items-center gap-2 bg-white text-primary font-semibold px-5 py-2 rounded-lg shadow-md hover:bg-gray-100 transition">
          <x-lucide-plus class="w-4 h-4" /> Book Now
        </a>
      </div>

      <!-- Opening Hours -->
      <div class="flex flex-col items-center justify-center p-6 text-center">
        <div class="flex items-center gap-3 mb-2">
          <span class="p-2 rounded-full bg-white/10">
            <x-lucide-clock class="w-6 h-6 text-white" aria-hidden="true" />
          </span>
          <span class="text-lg font-semibold">Opening Hours</span>
        </div>
        <p class="mt-1 text-sm leading-relaxed">
          Mon – Fri: 8:00am – 6:00pm<br>
          Sat: 9:00am – 2:00pm
        </p>
      </div>

    </div>
  </div>
</section>




<!-- 2. Our Story -->
<section class="w-full py-20 bg-white dark:bg-gray-900">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <h2 class="text-3xl font-bold mb-6 text-primary dark:text-primary-dark">
      Caring for You, Every Step of the Way
    </h2>
    <p class="max-w-3xl mx-auto text-gray-600 dark:text-gray-300 text-lg leading-relaxed mb-8">
      At HMS, healthcare is more than treatment — it’s about compassion, trust, and being there for our patients
      when it matters most. From state-of-the-art facilities to a team of dedicated professionals,
      we are committed to creating an environment where healing begins the moment you walk through our doors.
    </p>
    <a href="{{ route('about') }}"
       class="inline-block bg-primary hover:bg-primary-dark dark:bg-primary-dark dark:hover:bg-primary text-white px-6 py-3 rounded-lg shadow-md font-semibold transition">
      Discover Our Story
    </a>
  </div>
</section>


<!-- 3. Services Accordion -->
<section id="services" class="w-full py-20 bg-gray-50 dark:bg-gray-800">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class=" text-3xl font-bold text-center mb-12 text-primary dark:text-primary-dark">Our Services</h2>
        <div class="grid md:grid-cols-2 gap-8">
            @php
               $services = [
    ['title' => 'General Consultation', 'desc' => 'Comprehensive medical consultation for all ages.'],
    ['title' => 'Pediatrics', 'desc' => 'Expert care for infants, children, and adolescents.'],
    ['title' => 'Surgery', 'desc' => 'State-of-the-art surgical procedures with top surgeons.'],
    ['title' => 'Emergency Services', 'desc' => '24/7 emergency care with advanced equipment and trained staff.'],
    ['title' => 'Dental Care', 'desc' => 'Preventive and restorative dental services for healthy smiles.'],
    ['title' => 'Laboratory Services', 'desc' => 'Accurate diagnostic tests with quick turnaround times.'],
    ['title' => 'Radiology & Imaging', 'desc' => 'Advanced X-ray, CT scan, MRI, and ultrasound services.'],
    ['title' => 'Maternity Care', 'desc' => 'Comprehensive prenatal, delivery, and postnatal care.'],
    ['title' => 'Physiotherapy', 'desc' => 'Rehabilitation and therapy for recovery and mobility.'],
    ['title' => 'Pharmacy', 'desc' => 'Fully stocked in-house pharmacy for all prescriptions.'],
    ['title' => 'Cardiology', 'desc' => 'Specialized heart care, diagnostics, and treatments.'],
    ['title' => 'Orthopedics', 'desc' => 'Bone, joint, and spine care from expert orthopedists.'],
    ['title' => 'ENT (Ear, Nose, Throat)', 'desc' => 'Comprehensive ENT examinations and treatments.'],
    ['title' => 'Dermatology', 'desc' => 'Skin care treatments and cosmetic dermatology services.'],
    ['title' => 'Nutrition & Dietetics', 'desc' => 'Personalized dietary advice and nutrition counseling.'],
    ['title' => 'Oncology', 'desc' => 'Cancer screening, diagnosis, and treatment services.'],
    ['title' => 'Mental Health & Counseling', 'desc' => 'Professional psychological support and therapy sessions.'],
    ['title' => 'Urology', 'desc' => 'Specialized care for urinary tract and kidney conditions.'],
    ['title' => 'Ophthalmology', 'desc' => 'Eye care services including exams, surgery, and lenses.'],
    ['title' => 'Vaccination & Immunization', 'desc' => 'Routine and travel-related immunization programs.'],
];

            @endphp
            @foreach($services as $service)
                <div x-data="{ open: false }" class="border rounded shadow-sm p-4 bg-white dark:bg-gray-700">
                    <button @click="open = !open"
                            class="w-full text-left font-semibold text-lg flex justify-between items-center text-primary dark:text-primary-dark">
                        {{ $service['title'] }}
                        <span x-text="open ? '-' : '+'"></span>
                    </button>
                    <p x-show="open" class="mt-2 text-gray-600 dark:text-gray-300">{{ $service['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- 4. Doctors Carousel -->
<section x-data="doctorsCarousel()"
         x-init="startAutoplay()"
         @mouseenter="pauseAutoplay()"
         @mouseleave="startAutoplay()"
         class="w-full py-20 bg-white dark:bg-gray-900">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class=" text-3xl font-bold text-center mb-12 text-primary dark:text-primary-dark">Meet Our Doctors</h2>
        <div class="relative overflow-hidden">
            <div class="flex transition-transform duration-700 ease-in-out"
                 :style="`transform: translateX(-${current * pageWidth}px)`">
                @for($i=1; $i<=12; $i++)
                    <div class="w-[250px] flex-shrink-0 bg-gray-50 dark:bg-gray-700 rounded shadow p-4 text-center mx-2">
                        <img src="https://i.pravatar.cc/150/150/?doctor,portrait&sig={{$i}}"
                             class="mx-auto rounded-full mb-4" alt="Doctor {{$i}}">
                        <h3 class="font-semibold text-lg mb-1 text-primary dark:text-primary-dark">Dr. John Doe {{$i}}</h3>
                        <p class="text-gray-600 dark:text-gray-300">Specialist in Cardiology</p>
                    </div>
                @endfor
            </div>
        </div>
    </div>
</section>

<!-- 5. Departments -->
<section class="relative w-full py-20 bg-gray-50 dark:bg-gray-800">
    <div class="absolute inset-0">
        <img src="{{ asset('frontend/images/slider/1.png') }}" class="w-full h-full object-cover" alt="Departments Background">
        <div class="absolute inset-0 bg-black/90 dark:bg-black/80"></div>
    </div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class=" text-3xl font-bold text-center mb-12 text-primary dark:text-primary-dark">Our Departments</h2>
        <div class="grid md:grid-cols-3 gap-8">
            @foreach (['Cardiology', 'Pediatrics', 'Orthopedics'] as $department)
                <div class="bg-white/80 dark:bg-gray-700/80 rounded-2xl shadow-lg p-6 text-center hover:shadow-xl transition">
                    <div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center rounded-full bg-primary/10 dark:bg-primary-dark/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary dark:text-primary-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.1 0-2 .9-2 2v6h4v-6c0-1.1-.9-2-2-2zM12 2a10 10 0 00-7.07 17.07A10 10 0 1012 2z" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-100 mb-2">{{ $department }}</h3>
                    <p class="text-gray-600 dark:text-gray-300">Learn more about our {{ strtolower($department) }} services and how we care for patients.</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- 6. Blog -->
<section class="w-full py-20 bg-white dark:bg-gray-900">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class=" text-3xl font-bold text-center mb-12 text-primary dark:text-primary-dark">Latest News</h2>
        <div class="grid md:grid-cols-3 gap-8">
            @for($i=1; $i<=3; $i++)
                <div class="bg-white dark:bg-gray-700 rounded shadow overflow-hidden">
                    <img src="https://picsum.photos/400/200/?health,medical&sig={{$i}}" class="w-full h-48 object-cover" alt="Blog {{$i}}">
                    <div class="p-4">
                        <h3 class="font-semibold text-lg mb-2 text-primary dark:text-primary-dark">Blog Post Title {{$i}}</h3>
                        <p class="text-gray-600 dark:text-gray-300 mb-2">A short excerpt about the post content goes here. Lorem ipsum dolor sit amet...</p>
                        <a href="#" class="text-primary dark:text-primary-dark hover:underline font-semibold">Read More</a>
                    </div>
                </div>
            @endfor
        </div>
    </div>
</section>

<!-- 7. Testimonials -->
<section class="w-full py-20 bg-gray-50 dark:bg-gray-800">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class=" text-3xl font-bold text-center mb-12 text-primary dark:text-primary-dark">What Our Patients Say</h2>
        <div class="grid md:grid-cols-3 gap-8">
            @for($i=1; $i<=3; $i++)
                <div class="bg-white dark:bg-gray-700 rounded shadow p-6 text-center">
                    <p class="text-gray-600 dark:text-gray-300 mb-4">"Excellent service and compassionate staff. Highly recommend HMS!"</p>
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">Patient {{$i}}</h3>
                </div>
            @endfor
        </div>
    </div>
</section>

<!-- Scripts -->
<script>
    function heroSlider() {
        return {
            current: 0,
            slides: [
                { image: '{{asset("frontend/images/slider/1.png")}}', title: 'Your Health, Our Priority', subtitle: 'Providing world-class healthcare services with compassion and care.' },
                { image: '{{asset("frontend/images/slider/2.jpg")}}', title: 'Expert Doctors', subtitle: 'Our team of specialists ensures you get the best care possible.' },
                { image: '{{asset("frontend/images/slider/3.png")}}', title: 'Advanced Facilities', subtitle: 'Equipped with modern technology for accurate diagnosis and treatment.' }
            ],
            autoplay: null,
            startAutoplay() { this.autoplay = setInterval(() => { this.next(); }, 5000); },
            pauseAutoplay() { clearInterval(this.autoplay); },
            next() { this.current = (this.current + 1) % this.slides.length; },
            prev() { this.current = (this.current - 1 + this.slides.length) % this.slides.length; },
            goTo(index) { this.current = index; }
        }
    }

    function doctorsCarousel() {
        return {
            current: 0,
            cardWidth: 270,
            visible: 4,
            total: 12,
            autoplay: null,
            get pageWidth() { return this.cardWidth * this.visible; },
            get pageCount() { return Math.ceil(this.total / this.visible); },
            startAutoplay() { this.autoplay = setInterval(() => { this.next(); }, 4000); },
            pauseAutoplay() { clearInterval(this.autoplay); },
            next() { this.current = (this.current + 1) % this.pageCount; }
        }
    }


</script>

</x-frontend-layout>
