import * as React from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";

interface AvatarSliderProps {
    avatars: string[];
    fallbackText?: string;
    className?: string;
    size?: "sm" | "md" | "lg" | "xl" | "2xl" | "3xl";
    shape?: "circle" | "rectangle";
    showNavigation?: boolean;
    showDots?: boolean;
    autoPlay?: boolean;
    autoPlayInterval?: number;
}

const sizeClasses = {
    sm: "w-16 h-16",
    md: "w-24 h-24",
    lg: "w-32 h-32",
    xl: "w-48 h-48",
    "2xl": "w-64 h-64",
    "3xl": "w-80 h-80"
};

const rectangleSizeClasses = {
    sm: "w-20 h-16",
    md: "w-32 h-24",
    lg: "w-48 h-36",
    xl: "w-64 h-48",
    "2xl": "w-80 h-60",
    "3xl": "w-96 h-72"
};

export function AvatarSlider({
    avatars,
    fallbackText = "AV",
    className,
    size = "lg",
    shape = "circle",
    showNavigation = true,
    showDots = true,
    autoPlay = false,
    autoPlayInterval = 3000
}: AvatarSliderProps) {
    const [currentIndex, setCurrentIndex] = React.useState(0);
    const [isAutoPlaying, setIsAutoPlaying] = React.useState(autoPlay);

    // Auto-play functionality
    React.useEffect(() => {
        if (!isAutoPlaying || avatars.length <= 1) return;

        const interval = setInterval(() => {
            setCurrentIndex((prev) => (prev + 1) % avatars.length);
        }, autoPlayInterval);

        return () => clearInterval(interval);
    }, [isAutoPlaying, avatars.length, autoPlayInterval]);

    // Pause auto-play on hover
    const handleMouseEnter = () => setIsAutoPlaying(false);
    const handleMouseLeave = () => setIsAutoPlaying(autoPlay);

    const goToPrevious = () => {
        setCurrentIndex((prev) => (prev - 1 + avatars.length) % avatars.length);
    };

    const goToNext = () => {
        setCurrentIndex((prev) => (prev + 1) % avatars.length);
    };

    const goToSlide = (index: number) => {
        setCurrentIndex(index);
    };

    const getSizeClasses = () => {
        return shape === "rectangle" ? rectangleSizeClasses[size] : sizeClasses[size];
    };

    const getShapeClasses = () => {
        return shape === "rectangle" ? "rounded-lg" : "rounded-full";
    };

    if (!avatars || avatars.length === 0) {
        return (
            <div className={cn("flex items-center justify-center", className)}>
                <div className={cn(
                    "border-2 border-dashed border-muted-foreground/25 flex items-center justify-center bg-muted",
                    getSizeClasses(),
                    getShapeClasses()
                )}>
                    <span className="text-muted-foreground font-medium">
                        {fallbackText}
                    </span>
                </div>
            </div>
        );
    }

    if (avatars.length === 1) {
        return (
            <div className={cn("flex items-center justify-center", className)}>
                <div className={cn("overflow-hidden", getSizeClasses(), getShapeClasses())}>
                    <img
                        src={avatars[0]}
                        alt="Avatar"
                        className="w-full h-full object-cover"
                    />
                </div>
            </div>
        );
    }

    return (
        <div
            className={cn("relative group", className)}
            onMouseEnter={handleMouseEnter}
            onMouseLeave={handleMouseLeave}
        >
            {/* Main Avatar Display */}
            <div className="flex items-center justify-center">
                <div className={cn("overflow-hidden", getSizeClasses(), getShapeClasses())}>
                    <img
                        src={avatars[currentIndex]}
                        alt={`Avatar ${currentIndex + 1}`}
                        className="w-full h-full object-cover"
                    />
                </div>
            </div>

            {/* Navigation Arrows */}
            {showNavigation && avatars.length > 1 && (
                <>
                    <Button
                        variant="outline"
                        size="sm"
                        className="absolute left-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity bg-background/80 backdrop-blur-sm"
                        onClick={goToPrevious}
                    >
                        <ChevronLeft className="w-4 h-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        className="absolute right-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity bg-background/80 backdrop-blur-sm"
                        onClick={goToNext}
                    >
                        <ChevronRight className="w-4 h-4" />
                    </Button>
                </>
            )}

            {/* Dots Indicator */}
            {showDots && avatars.length > 1 && (
                <div className="absolute bottom-2 left-1/2 -translate-x-1/2 flex space-x-1">
                    {avatars.map((_, index) => (
                        <button
                            key={index}
                            className={cn(
                                "w-2 h-2 rounded-full transition-all",
                                index === currentIndex
                                    ? "bg-primary"
                                    : "bg-muted-foreground/30 hover:bg-muted-foreground/50"
                            )}
                            onClick={() => goToSlide(index)}
                        />
                    ))}
                </div>
            )}

            {/* Image Counter */}
            <div className="absolute top-2 right-2 bg-background/80 backdrop-blur-sm px-2 py-1 rounded text-xs font-medium">
                {currentIndex + 1} / {avatars.length}
            </div>
        </div>
    );
}
