<x-frontend-layout title="Blog">
    <div class="max-w-7xl mx-auto px-4 py-10">
        <!-- Page title + description -->
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="text-4xl font-extrabold mb-4 text-primary tracking-tight">
                Insights & Updates
            </h2>
            <p class="text-gray-600 dark:text-gray-300 text-lg leading-relaxed">
                Welcome to our blog — your hub for the latest news, expert tips, and in-depth insights
                on healthcare management and innovation. Stay informed, inspired, and ahead of the curve.
            </p>
        </div>


        <!-- Blog Layout -->
        <div class="grid lg:grid-cols-3 gap-4">
            <!-- Blog posts (2 columns) -->
            <div class="lg:col-span-2 grid sm:grid-cols-3 gap-3">

                <!-- Post card -->
                <article class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden hover:shadow-lg transition">
                    <img src="https://picsum.photos/600/400?random=1" alt="Post image" class="w-full h-30 object-cover">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-primary">Blog Post Title</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Quick intro to the post goes here. Keep it engaging to pull readers in.
                        </p>
                        <a href="#" class="inline-block mt-3 text-sm font-medium text-primary hover:underline">
                            Read more →
                        </a>
                    </div>
                </article>

                <!-- More dummy posts -->
                <article class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden hover:shadow-lg transition">
                    <img src="https://picsum.photos/600/400?random=2" alt="Post image" class="w-full h-48 object-cover">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-primary">Another Blog Post</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Share something insightful, helpful, or fun for your readers.
                        </p>
                        <a href="#" class="inline-block mt-3 text-sm font-medium text-primary hover:underline">
                            Read more →
                        </a>
                    </div>
                </article>

                <article class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden hover:shadow-lg transition">
                    <img src="https://picsum.photos/600/400?random=3" alt="Post image" class="w-full h-48 object-cover">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-primary">Healthcare Trends</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Discover the latest in healthcare innovation and technology.
                        </p>
                        <a href="#" class="inline-block mt-3 text-sm font-medium text-primary hover:underline">
                            Read more →
                        </a>
                    </div>
                </article>
                <!-- Post card -->
                <article class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden hover:shadow-lg transition">
                    <img src="https://picsum.photos/600/400?random=4" alt="Post image" class="w-full h-30 object-cover">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-primary">Blog Post Title</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Quick intro to the post goes here. Keep it engaging to pull readers in.
                        </p>
                        <a href="#" class="inline-block mt-3 text-sm font-medium text-primary hover:underline">
                            Read more →
                        </a>
                    </div>
                </article>

                <!-- More dummy posts -->
                <article class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden hover:shadow-lg transition">
                    <img src="https://picsum.photos/600/400?random=5" alt="Post image" class="w-full h-48 object-cover">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-primary">Another Blog Post</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Share something insightful, helpful, or fun for your readers.
                        </p>
                        <a href="#" class="inline-block mt-3 text-sm font-medium text-primary hover:underline">
                            Read more →
                        </a>
                    </div>
                </article>

                <article class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden hover:shadow-lg transition">
                    <img src="https://picsum.photos/600/400?random=6" alt="Post image" class="w-full h-48 object-cover">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-primary">Healthcare Trends</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Discover the latest in healthcare innovation and technology.
                        </p>
                        <a href="#" class="inline-block mt-3 text-sm font-medium text-primary hover:underline">
                            Read more →
                        </a>
                    </div>
                </article>
            </div>

            <!-- Sidebar -->
            <aside class="lg:col-span-1 space-y-4">
                <!-- Search -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                    <h4 class="text-lg text-primary font-semibold mb-3">Search</h4>
                    <input type="text" placeholder="Search blog..."
                           class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 focus:ring-primary focus:border-primary">
                </div>

                <!-- Categories -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                    <h4 class="text-lg font-semibold mb-3 text-primary">Categories</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-primary">Healthcare</a></li>
                        <li><a href="#" class="hover:text-primary">Administration</a></li>
                        <li><a href="#" class="hover:text-primary">Technology</a></li>
                        <li><a href="#" class="hover:text-primary">Innovation</a></li>
                    </ul>
                </div>

                <!-- Recent posts -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                    <h4 class="text-lg font-semibold mb-3 text-primary">Recent Posts</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="#" class="block hover:text-primary">5 Tips for Better Hospital Management</a></li>
                        <li><a href="#" class="block hover:text-primary">Why Digital Records Are the Future</a></li>
                        <li><a href="#" class="block hover:text-primary">How Nurses Can Leverage Tech</a></li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</x-frontend-layout>
