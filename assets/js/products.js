// PondyVastra Product Catalog Database
const PONDY_PRODUCTS = [
  {
    id: "pv-01",
    name: "Gulabi Zari Chanderi Silk Kurta Set",
    category: "Kurtis & Kurta Sets",
    categorySlug: "kurtis",
    price: 2499,
    originalPrice: 4999,
    discount: "50% OFF",
    rating: 4.9,
    reviewsCount: 148,
    image: "./assets/images/kurta-set.jpg",
    gallery: [
      "./assets/images/kurta-set.jpg",
      "./assets/images/hero-banner.jpg"
    ],
    badge: "Bestseller",
    description: "Handcrafted in Pondicherry, this ruby crimson Chanderi silk kurta features intricate golden zari embroidery on the yoke and sleeves, paired with tailored cigarette pants and a glistening tissue gold dupatta.",
    fabric: "Chanderi Silk & Gold Tissue",
    fit: "Straight Fit",
    sizes: ["XS", "S", "M", "L", "XL", "XXL"],
    colors: [
      { name: "Ruby Crimson", code: "#be123c" },
      { name: "Royal Navy", code: "#1e3a8a" },
      { name: "Emerald Green", code: "#065f46" }
    ],
    inStock: true
  },
  {
    id: "pv-02",
    name: "Pondicherry French Rose Temple Silk Saree",
    category: "Sarees",
    categorySlug: "sarees",
    price: 4299,
    originalPrice: 7999,
    discount: "46% OFF",
    rating: 4.9,
    reviewsCount: 212,
    image: "./assets/images/saree.jpg",
    gallery: [
      "./assets/images/saree.jpg",
      "./assets/images/hero-banner.jpg"
    ],
    badge: "Heritage Craft",
    description: "An ode to Pondicherry's rich Indo-French heritage. Woven with pure mulberry silk yarns, highlighted with opulent temple architectural zari motifs along the pallu and contrasting rich wine borders.",
    fabric: "Pure Mulberry Silk",
    fit: "Free Size (6.3m with blouse piece)",
    sizes: ["Free Size"],
    colors: [
      { name: "Blush Rose & Wine", code: "#9f1239" },
      { name: "Mustard Gold", code: "#d97706" },
      { name: "Teal Peacock", code: "#0f766e" }
    ],
    inStock: true
  },
  {
    id: "pv-03",
    name: "Auroville Meadow Tiered Georgette Dress",
    category: "Dresses",
    categorySlug: "dresses",
    price: 1899,
    originalPrice: 3499,
    discount: "45% OFF",
    rating: 4.8,
    reviewsCount: 96,
    image: "./assets/images/indowestern-dress.jpg",
    gallery: [
      "./assets/images/indowestern-dress.jpg",
      "./assets/images/hero-banner.jpg"
    ],
    badge: "Trending",
    description: "Breezy and effortlessly romantic. Features botanical watercolor floral motifs on sheer georgette, an empire waistline with matching fabric buckle belt, and graceful tiered ruffles.",
    fabric: "Poly Georgette with Shantoon Lining",
    fit: "Flared Maxi Fit",
    sizes: ["XS", "S", "M", "L", "XL"],
    colors: [
      { name: "Blush Peach", code: "#fbcfe8" },
      { name: "Sky Mist", code: "#bae6fd" },
      { name: "Sage Mint", code: "#a7f3d0" }
    ],
    inStock: true
  },
  {
    id: "pv-04",
    name: "Royale Pondy Courtyard Embroidered Anarkali",
    category: "Kurtis & Kurta Sets",
    categorySlug: "kurtis",
    price: 3699,
    originalPrice: 6999,
    discount: "47% OFF",
    rating: 5.0,
    reviewsCount: 310,
    image: "./assets/images/hero-banner.jpg",
    gallery: [
      "./assets/images/hero-banner.jpg",
      "./assets/images/kurta-set.jpg"
    ],
    badge: "Signature Piece",
    description: "Designed exclusively for festive galas. Lavish kalis adorned with hand-stitched gotta patti, dabka work, and delicate sequin borders. Comes with a regal scallop-hem dupatta.",
    fabric: "Raw Silk Blend with Soft Net Dupatta",
    fit: "Floor Length Anarkali",
    sizes: ["S", "M", "L", "XL", "XXL"],
    colors: [
      { name: "Rose Petal Pink", code: "#e11d48" },
      { name: "Deep Maroon", code: "#881337" }
    ],
    inStock: true
  },
  {
    id: "pv-05",
    name: "French Quarters Printed Ethnic Co-ord Set",
    category: "Co-ord Sets",
    categorySlug: "coord-sets",
    price: 1799,
    originalPrice: 2999,
    discount: "40% OFF",
    rating: 4.7,
    reviewsCount: 78,
    image: "https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=800&q=80",
    gallery: [
      "https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=800&q=80"
    ],
    badge: "New Arrival",
    description: "Contemporary comfort meets traditional block prints. Notch collar tunic with flared palazzo pants in breathable organic modal cotton.",
    fabric: "100% Breathable Modal Cotton",
    fit: "Relaxed Comfort Fit",
    sizes: ["S", "M", "L", "XL", "XXL"],
    colors: [
      { name: "Indigo Blue", code: "#1e40af" },
      { name: "Terracotta Rust", code: "#c2410c" }
    ],
    inStock: true
  },
  {
    id: "pv-06",
    name: "Maharani Zari Velvet Bridal Lehenga",
    category: "Lehengas",
    categorySlug: "lehengas",
    price: 8999,
    originalPrice: 15999,
    discount: "44% OFF",
    rating: 4.9,
    reviewsCount: 84,
    image: "https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=800&q=80",
    gallery: [
      "https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=800&q=80"
    ],
    badge: "Luxury Wedding",
    description: "A showstopper bridal lehenga in rich mulberry wine micro-velvet, heavily embroidered with antique gold tilla and zardozi threadwork. Features double-can-can flare.",
    fabric: "Royal Micro Velvet & Organza Dupatta",
    fit: "Customizable Semi-Stitched Flare",
    sizes: ["Free Size (Semi-Stitched)"],
    colors: [
      { name: "Wine Red", code: "#831843" },
      { name: "Bottle Green", code: "#14532d" }
    ],
    inStock: true
  },
  {
    id: "pv-07",
    name: "Pondy Promenade Cotton Floral Kurti",
    category: "Kurtis & Kurta Sets",
    categorySlug: "kurtis",
    price: 999,
    originalPrice: 1999,
    discount: "50% OFF",
    rating: 4.6,
    reviewsCount: 195,
    image: "https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?auto=format&fit=crop&w=800&q=80",
    gallery: [
      "https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?auto=format&fit=crop&w=800&q=80"
    ],
    badge: "Daily Chic",
    description: "Everyday luxury crafted in 60s combed cotton. Adorned with delicate wooden buttons and French knot embroidery along the split mandarin collar.",
    fabric: "100% Combed Slub Cotton",
    fit: "A-Line Regular Fit",
    sizes: ["XS", "S", "M", "L", "XL", "XXL", "3XL"],
    colors: [
      { name: "Ivory & Magenta", code: "#be185d" },
      { name: "Powder Blue", code: "#38bdf8" }
    ],
    inStock: true
  },
  {
    id: "pv-08",
    name: "Kashmiri Aari Embroidered Organza Dupatta",
    category: "Dupattas & Shawls",
    categorySlug: "dupattas",
    price: 1299,
    originalPrice: 2499,
    discount: "48% OFF",
    rating: 4.8,
    reviewsCount: 62,
    image: "https://images.unsplash.com/photo-1609357605129-26f69add5d6e?auto=format&fit=crop&w=800&q=80",
    gallery: [
      "https://images.unsplash.com/photo-1609357605129-26f69add5d6e?auto=format&fit=crop&w=800&q=80"
    ],
    badge: "Handloom",
    description: "Sheer crystal organza dupatta with intricate multi-color floral Aari embroidery and pearl beaded scalloped lace edges. Adds instant royalty to any plain kurta.",
    fabric: "Pure Sheer Organza",
    fit: "Length: 2.5 meters",
    sizes: ["Free Size"],
    colors: [
      { name: "Champagne Cream", code: "#fef08a" },
      { name: "Blush Coral", code: "#f43f5e" }
    ],
    inStock: true
  }
];

// Helper functions for state
function getProductById(id) {
  return PONDY_PRODUCTS.find(p => p.id === id);
}

function formatPrice(amount) {
  return "₹" + Number(amount).toLocaleString('en-IN');
}
