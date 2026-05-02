import { useState } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { Carousel, CarouselContent, CarouselItem, CarouselPrevious, CarouselNext } from "./ui/carousel";

export function ScreenshotCarousel({ type = "console" }) {
  const screenshots = [
    {
      id: 1,
      title: type === "console" ? "Dashboard Overview" : "CLI Registration",
      src: `/images/${type}/screenshot-1.svg`,
    },
    {
      id: 2,
      title: type === "console" ? "Configuration Management" : "Configuration Setup",
      src: `/images/${type}/screenshot-2.svg`,
    },
    {
      id: 3,
      title: type === "console" ? "RBAC Administration" : "Validation Check",
      src: `/images/${type}/screenshot-3.svg`,
    },
    {
      id: 4,
      title: type === "console" ? "API Documentation" : "System Registration",
      src: `/images/${type}/screenshot-4.svg`,
    },
    {
      id: 5,
      title: type === "console" ? "Queue Management" : "Service Discovery",
      src: `/images/${type}/screenshot-5.svg`,
    },
    {
      id: 6,
      title: type === "console" ? "Database Monitoring" : "Health Watch",
      src: `/images/${type}/screenshot-6.svg`,
    },
    {
      id: 7,
      title: type === "console" ? "Cache Visualization" : "Port Monitoring",
      src: `/images/${type}/screenshot-7.svg`,
    },
    {
      id: 8,
      title: type === "console" ? "Job Replay Interface" : "Config Backup",
      src: `/images/${type}/screenshot-8.svg`,
    },
    {
      id: 9,
      title: type === "console" ? "Audit Logs" : "Deregistration",
      src: `/images/${type}/screenshot-9.svg`,
    },
    {
      id: 10,
      title: type === "console" ? "System Settings" : "Reactivation",
      src: `/images/${type}/screenshot-10.svg`,
    },
  ];

  const [currentIndex, setCurrentIndex] = useState(0);

  const goToPrevious = () => {
    setCurrentIndex((prevIndex) =>
      prevIndex === 0 ? screenshots.length - 1 : prevIndex - 1
    );
  };

  const goToNext = () => {
    setCurrentIndex((prevIndex) =>
      prevIndex === screenshots.length - 1 ? 0 : prevIndex + 1
    );
  };

  return (
    <div className="w-full">
      <div className="relative rounded-2xl border border-zinc-800 bg-[#101012] overflow-hidden">
        {/* Main image display */}
        <div className="relative aspect-video bg-zinc-950 flex items-center justify-center overflow-hidden">
          <img
            src={screenshots[currentIndex].src}
            alt={screenshots[currentIndex].title}
            className="w-full h-full object-cover"
            onError={(e) => {
              e.target.src =
                "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='600'%3E%3Crect fill='%2327272a' width='800' height='600'/%3E%3Ctext x='50%25' y='50%25' font-family='monospace' font-size='16' fill='%23a1a1a1' text-anchor='middle' dominant-baseline='middle'%3E" +
                encodeURIComponent(screenshots[currentIndex].title) +
                "%3C/text%3E%3C/svg%3E";
            }}
          />
        </div>

        {/* Navigation buttons */}
        <div className="absolute inset-0 flex items-center justify-between px-4 pointer-events-none">
          <button
            onClick={goToPrevious}
            className="pointer-events-auto p-2 rounded-full bg-amber-500/20 text-amber-500 hover:bg-amber-500/40 transition-colors"
            aria-label="Previous screenshot"
          >
            <ChevronLeft size={24} />
          </button>
          <button
            onClick={goToNext}
            className="pointer-events-auto p-2 rounded-full bg-amber-500/20 text-amber-500 hover:bg-amber-500/40 transition-colors"
            aria-label="Next screenshot"
          >
            <ChevronRight size={24} />
          </button>
        </div>

        {/* Screenshot info and counter */}
        <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-6">
          <div className="flex justify-between items-end">
            <div>
              <h3 className="text-xl font-semibold text-zinc-50 mb-1">
                {screenshots[currentIndex].title}
              </h3>
              <p className="text-sm text-zinc-400">
                {currentIndex + 1} of {screenshots.length}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Thumbnail strip */}
      <div className="mt-4 flex gap-2 overflow-x-auto pb-2">
        {screenshots.map((screenshot, index) => (
          <button
            key={screenshot.id}
            onClick={() => setCurrentIndex(index)}
            className={`flex-shrink-0 w-20 h-20 rounded-lg border-2 transition-all overflow-hidden ${
              index === currentIndex
                ? "border-amber-500 opacity-100"
                : "border-zinc-700 opacity-60 hover:opacity-100"
            }`}
            aria-label={`Go to ${screenshot.title}`}
          >
            <img
              src={screenshot.src}
              alt={screenshot.title}
              className="w-full h-full object-cover"
              onError={(e) => {
                e.target.src =
                  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80'%3E%3Crect fill='%2327272a' width='80' height='80'/%3E%3C/svg%3E";
              }}
            />
          </button>
        ))}
      </div>
    </div>
  );
}
